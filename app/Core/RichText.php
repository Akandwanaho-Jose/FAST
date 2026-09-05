<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use DOMDocument;use DOMElement;use DOMNode;

final class RichText
{
    private const ALLOWED=['div','p','br','h2','h3','h4','strong','b','em','i','u','s','ul','ol','li','blockquote','a','figure','figcaption','img'];
    public static function render(mixed$value):string{$html=trim((string)$value);if($html==='')return'';if(!preg_match('/<\/?[a-z][^>]*>/i',$html))return nl2br(View::escape($html));$dom=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8"><div id="rich-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);libxml_clear_errors();libxml_use_internal_errors($previous);$root=$dom->getElementById('rich-root');if(!$root instanceof DOMElement)return View::escape(strip_tags($html));self::clean($root);$output='';foreach(iterator_to_array($root->childNodes)as$node)$output.=$dom->saveHTML($node);return$output;}
    private static function clean(DOMNode$node):void{foreach(iterator_to_array($node->childNodes)as$child){if(!$child instanceof DOMElement)continue;$tag=strtolower($child->tagName);if(in_array($tag,['script','style','iframe','object','embed','svg','math'],true)){$node->removeChild($child);continue;}if(!in_array($tag,self::ALLOWED,true)){self::clean($child);while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;}$originalHref=$tag==='a'?$child->getAttribute('href'):'';$originalSrc=$tag==='img'?$child->getAttribute('src'):'';$originalAlt=$tag==='img'?$child->getAttribute('alt'):'';foreach(iterator_to_array($child->attributes)as$attribute)$child->removeAttribute($attribute->name);if($tag==='a'&&self::safeUrl($originalHref)){$child->setAttribute('href',$originalHref);if(preg_match('#^https?://#i',$originalHref)){$child->setAttribute('target','_blank');$child->setAttribute('rel','noopener noreferrer');}}if($tag==='img'){if(!self::safeUrl($originalSrc)){$node->removeChild($child);continue;}$child->setAttribute('src',$originalSrc);$child->setAttribute('alt',$originalAlt);$child->setAttribute('loading','lazy');}if($tag==='figure')$child->setAttribute('class','editorial-inline-figure');self::clean($child);}}
    private static function safeUrl(string$url):bool{return$url!==''&&(str_starts_with($url,'/')||str_starts_with($url,'#')||preg_match('#^(https?://|mailto:)#i',$url)===1);}
}
