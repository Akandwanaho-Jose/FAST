<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use RuntimeException;

final class DocumentUploadService
{
    private const TYPES=['application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.ms-excel'=>'xls','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx','text/csv'=>'csv','text/plain'=>'txt'];
    public function __construct(private readonly string$publicPath,private readonly int$maximumBytes){}
    /** @param array<string,mixed>|null$file @return array{upload:?array,errors:list<string>} */public function validate(?array$file,bool$required=true):array{if($file===null||(int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return['upload'=>null,'errors'=>$required?['Choose a document to upload.']:[]];if((int)($file['error']??1)!==UPLOAD_ERR_OK)return['upload'=>null,'errors'=>['The document upload did not complete.']];$tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);if(!is_file($tmp)||$size<1||$size>$this->maximumBytes)return['upload'=>null,'errors'=>['Document must be a readable file no larger than '.(int)ceil($this->maximumBytes/1048576).' MB.']];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!is_string($mime)||!isset(self::TYPES[$mime]))return['upload'=>null,'errors'=>['Use a PDF, Word, Excel, CSV, or text document.']];$name=basename(str_replace('\\','/',(string)($file['name']??'document')));$name=mb_substr(preg_replace('/[^\pL\pN._ -]+/u','-',$name)?:'document',0,255);return['upload'=>['tmp_name'=>$tmp,'original_name'=>$name,'mime_type'=>$mime,'extension'=>self::TYPES[$mime],'size'=>$size],'errors'=>[]];}
    /** @param array<string,mixed>$upload @return array{original_name:string,stored_name:string,file_path:string,mime_type:string,file_size:int,absolute_path:string} */public function store(array$upload):array{$folder=date('Y/m');$dir=rtrim($this->publicPath,'/\\').'/uploads/documents/'.$folder;if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Document directory is unavailable.');$name=bin2hex(random_bytes(20)).'.'.$upload['extension'];$absolute=$dir.DIRECTORY_SEPARATOR.$name;$moved=is_uploaded_file((string)$upload['tmp_name'])?move_uploaded_file((string)$upload['tmp_name'],$absolute):rename((string)$upload['tmp_name'],$absolute);if(!$moved)throw new RuntimeException('Document could not be stored.');return['original_name'=>(string)$upload['original_name'],'stored_name'=>$name,'file_path'=>'public/uploads/documents/'.$folder.'/'.$name,'mime_type'=>(string)$upload['mime_type'],'file_size'=>(int)$upload['size'],'absolute_path'=>$absolute];}public function remove(?string$p):void{if(is_string($p)&&is_file($p))@unlink($p);}
}
