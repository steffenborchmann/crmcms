<?php
namespace Tualo\Office\CrmCms\CMSMiddleware;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\CrmCms\CRM;
use Tualo\Office\CrmCms\Account;
use Michelf\MarkdownExtra;
use Tualo\Office\DS\DSFileHelper;
use Ramsey\Uuid\Uuid;
class TranslatorUpload {
    public static function pages($file):mixed{
        $params = ['gs'];
        $params[] =  '-q';
        $params[] =  '-dNOPAUSE';
        $params[] =  '-dNOSAFER';
        $params[] =  '-dNODISPLAY';
        $params[] =  '-c';
        $params[] =  "\"($file) (r) file runpdfbegin pdfpagecount = quit\"";
        exec( implode(' ',$params),$gsresult);
        if (count( $gsresult ) == 1) return intval($gsresult[0]);
        return false;
    }

    public static function words($file):mixed{
        exec('pdftotext '.$file , $nix, $state);
        $params = ['wc'];
        $params[] =  '-w';
        $params[] =  " \"$file.txt\" | awk '{print $1}'";
        exec( implode(' ',$params),$wcresult);
        if (count( $wcresult ) == 1) return intval($wcresult[0]);
        return false;
    }

    public static function db() { return App::get('session')->getDB(); }
    public static function run(&$request,&$result){
        if (
            isset($_FILES['userfile'])
        ){
            @session_start();
            $db = self::db();
            $crm = CRM::getInstance();
            if (
                !is_null($crm->get('account')) &&
                $crm->get('account')->isLoggedIn() &&
                $crm->get('account')->get('login_type')=='translator'
            ) {
           
            }
            if (
                isset($_REQUEST['source_lang']) &&
                isset($_REQUEST['destination_lang']) &&
                $crm->get('account')->isLoggedIn() &&
                $crm->get('account')->get('login_type')=='customer'
            ) {

                $hash = $db->singleRow('select concat("TRNS",substring(replace(replace(replace(now()," ",""),"-",""),":",""),1,12)) project, uuid() translation',[],'');
                $db->direct('insert into projects (id,created) values ({project},now())',$hash);
                $hash+=[
                    'source_language'       =>  $_REQUEST['source_lang'],
                    'destination_language'  =>  $_REQUEST['destination_lang'],
                    'kundennummer'          =>  $crm->get('account')->get('kundennummer'),
                    'kostenstelle'          =>  $crm->get('account')->get('kostenstelle')
                ];
                $db->direct('insert into translations (id,project,source_language,destination_language,created) values ({translation},{project},{source_language},{destination_language},now())',$hash);
                $db->direct('insert into translations_kunden (translation,kundennummer,kostenstelle) values ({translation},{kundennummer},{kostenstelle} )',$hash);
                
                $local_file_name = App::get('tempPath').'/.ht_'.(Uuid::uuid4())->toString();
                DSFileHelper::uploadFileToDB('userfile','translations',[
                    'fieldName'=>'translations__document',
                    'translations__id'=>$hash['translation']
                ],$local_file_name );
                $words = self::words($local_file_name);
                $hash['words']=$words;
                if($count = self::pages($local_file_name)){
                    $hash['count']=$count;
                    $db->direct('update translations set source_pages = {count}, source_words = {words} where id={translation}',$hash);
                }

                if (file_exists($local_file_name)){ unlink($local_file_name); }
                if (file_exists($local_file_name.'txt')){ unlink($local_file_name.'txt'); }
                $crm->set('type','upload_success');
            }
        }

    }
}

