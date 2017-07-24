<?php
/**
 * DokuWiki Plugin xml
 *
 * @author   Patrick Bueker <Patrick@patrickbueker.de>
 * @author   Danny Lin <danny0838@gmail.com>
 * @license  GPLv2 or later (http://www.gnu.org/licenses/gpl.html)
 *
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
require_once DOKU_INC . 'inc/parser/renderer.php';

class renderer_plugin_asciidoc extends Doku_Renderer {

    function __construct() {
        $this->reset();
    }

    /**
     * Allows renderer to be used again. Clean out any per-use values.
     */
    function reset() {
        $this->info = array(
            'cache' => true, // may the rendered result cached?
            'toc'   => false, // render the TOC?
        );
        $this->precedinglevel = array();
        $this->sectionlevel = 1;
        $this->helper         = &plugin_load('helper','asciidoc');
        $this->keywords = array();
        $this->doctitle = '';
        $this->doc            = '';
        $this->tagStack       = array();
        $this->listKindStack      = array();
        $this->quoteStack      = array();
        $this->relfileprefix = '';
        $this->levelMap = array();
        $this->levelMaxReached = 1;

        $this->smiley_to_emoticon = array(
          '>:(' => 'angry', '>:-(' => 'angry',
          ':")' => 'blush', ':-")' => 'blush',
          '</3' => 'broken_heart', '<\\3' => 'broken_heart',
          ':/' => 'confused', ':-/' => 'confused', ':-\\' => 'confused', ':-\\' => 'confused',// twemoji shows question
          ":'(" => 'cry', ":'-(" => 'cry', ':,(' => 'cry', ':,-(' => 'cry',
          ':(' => 'frowning', ':-(' => 'frowning',
          '<3' => 'heart',
          ']:(' => 'imp', ']:-(' => 'imp',
          'o:)' => 'innocent', 'O:)' => 'innocent', 'o:-)' => 'innocent', 'O:-)' => 'innocent', '0:)' => 'innocent', '0:-)' => 'innocent',
          ":')" => 'joy', ":'-)" => 'joy', ':,)' => 'joy', ':,-)' => 'joy', ":'D" => 'joy', ":'-D" => 'joy', ':,D' => 'joy', ':,-D' => 'joy',
          ':*' => 'kissing', ':-*' => 'kissing',
          'x-)'=> 'laughing', 'X-)'=> 'laughing',
          ':|' => 'neutral_face', ':-|' => 'neutral_face',
          ':o' => 'open_mouth', ':-o' => 'open_mouth', ':O' => 'open_mouth', ':-O' => 'open_mouth',
          ':@' => 'rage', ':-@' => 'rage',
          ':D' => 'smile', ':-D'  => 'smile',
          ':)' => 'smiley', ':-)' => 'smiley',
          ']:)' => 'smiling_imp', ']:-)' => 'smiling_imp',
          ":,'(" => 'sob', ":,'-(" => 'sob', ';(' => 'sob', ';-(' => 'sob',
          ':P' => 'stuck_out_tongue', ':-P' => 'stuck_out_tongue',
          '8-)' => 'sunglasses', 'B-)' => 'sunglasses',
          ',:(' => 'sweat', ',:-(' => 'sweat',
          ',:)' => 'sweat_smile', ',:-)'  => 'sweat_smile',
          ':s' => 'unamused', ':-S' => 'unamused', ':z' => 'unamused', ':-Z' => 'unamused', ':$' => 'unamused', ':-$' => 'unamused',
          ';)' => 'wink', ';-)' => 'wink'
        );
    }

    /**
     * Returns the format produced by this renderer.
     *
     * @return string
     */
    function getFormat(){return 'asciidoc';}

    /**
     * handle plugin rendering
     */
    function plugin($name,$data){
        $plugin =& plugin_load('syntax',$name);
        if ($plugin == null) return;
        if ($this->helper->_asciidoc_extension($this,$name,$data)) return;
        $plugin->render($this->getFormat(),$this,$data);
    }

    function document_start() {
        global $conf;
        global $ID;
        global $INFO;
        //TODO add title, author, revision, ...
        // store the content type headers in metadata
        // prepare date and path
        $fn = $INFO['filepath'];
        $fn = str_replace(fullpath($conf['datadir']).'/', '', $fn);
        $fn = str_replace('.txt', '.adoc', $fn);
        $fn = utf8_decodeFN($fn);

        $this->doc = '';
        $this->relfileprefix = str_repeat("../", substr_count ( $ID , ':'));

        $headers = array(
            'Content-Type' => 'text/asciidoc; charset=utf-8;',
            'Content-Disposition' => 'attachment; filename="'.$fn.'";',
        );
        p_set_metadata($ID,array('format' => array('asciidoc' => $headers) ));
    }

    function document_end() {
      global $ID;
      global $INFO;
      //TODO add title, author, revision, ...
      if (empty($this->doctitle)) {
        $this->doctitle = $this->_simpleTitle($ID);
      }
      $top = '';
      $top .= '= ' . $this->doctitle .DOKU_LF;
      $top .= ':author: ' . $INFO['editor'] .DOKU_LF;
      $top .= ':revnumber: ' . $INFO['rev'] .DOKU_LF;
      $top .= ':revdate: ' . dformat($INFO['lastmod']) .DOKU_LF;
      if (!empty($this->keywords)) {
          $top .= ':keywords: ' . implode(', ', $this->keywords) . DOKU_LF;
      }
      if (!empty($this->relfileprefix)){
        $top .= ':relfileprefix: ' . $this->relfileprefix . DOKU_LF;
        $top .= ':imagesdir: ' . rtrim($this->relfileprefix, '/') . DOKU_LF;
      }
      //:relfileprefix: ../
      $top .= 'ifdef::env-github,env-browser[:outfilesuffix: .adoc]'  . DOKU_LF;
      $top .= DOKU_LF;
      $this->doc = $top . $this->doc;
    }

    function header($text, $level, $pos) {
        if (!$text) return; //skip empty headlines
        if ($level == 1 && empty($this->doctitle)) {
          $this->doctitle = $text;
        } else {
          $l = $this->levelMap[$level];
          if (!$l) {
            $this->levelMaxReached += 1;
            $l = $this->levelMaxReached;
            $this->levelMap[$level] = $l;
          }
          $this->doc .= DOKU_LF . DOKU_LF . str_repeat("=", $l) . ' ' . $text. DOKU_LF;
        }
    }

    function section_open($level) {
        array_push($this->precedinglevel,$level);
        $this->sectionlevel = $this->sectionlevel + 1;
    }

    function section_close() {
      $this->sectionlevel = $this->sectionlevel - 1;
    }

    function nocache() {
        $this->info['cache'] = false;
        $this->doc .= '<macro name="nocache" />'.DOKU_LF;
    }

    function notoc() {
        $this->info['toc'] = false;
        $this->doc .= '<macro name="notoc" />'.DOKU_LF;
    }

    function cdata($text) {
        $this->doc .= $this->_xmlEntities($text);
    }

    function p_open() {
        $this->doc .= DOKU_LF;
        $this->_openTag($this, 'p_close', array());
    }

    function p_close() {
        $this->_closeTags($this, __FUNCTION__);
        if ($this->doc[-1] != DOKU_LF) $this->doc .= DOKU_LF;
    }

    function linebreak() {
        $this->doc .= '+';
    }

    function hr() {
        $this->doc .= "'''".DOKU_LF;
    }

    function strong_open() {
        $this->doc .= '*';
        $this->_openTag($this, 'strong_close', array());
    }

    function strong_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= '*';
    }

    function emphasis_open() {
        $this->doc .= '_';
        $this->_openTag($this, 'emphasis_close', array());
    }

    function emphasis_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= '_';
    }

    function underline_open() {
        $this->doc .= '+++<u>';
        $this->_openTag($this, 'underline_close', array());
    }

    function underline_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= '</u>+++';
    }

    function monospace_open() {
        $this->doc .= '`';
        $this->_openTag($this, 'monospace_close', array());
    }

    function monospace_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= '`';
    }

    function subscript_open() {
        $this->doc .= '~';
        $this->_openTag($this, 'subscript_close', array());
    }

    function subscript_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= '~';
    }

    function superscript_open() {
        $this->doc .= '^';
        $this->_openTag($this, 'superscript_close', array());
    }

    function superscript_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= '^';
    }

    function deleted_open() {
        $this->doc .= '+++<strike>';
        $this->_openTag($this, 'deleted_close', array());
    }

    function deleted_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= '</strike>+++';
    }

    function footnote_open() {
        $this->doc .= 'footnote:[';
        $this->_openTag($this, 'footnote_close', array());
    }

    function footnote_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= ']';
    }

    function listu_open() {
        array_unshift($this->listKindStack, "*");
        $this->_openTag($this, 'listu_close', array());
    }

    function listu_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= DOKU_LF;
        array_shift($this->listKindStack);
    }

    function listo_open() {
        array_unshift($this->listKindStack, ".");
        $this->_openTag($this, 'listo_close', array());
    }

    function listo_close() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= DOKU_LF;
        array_shift($this->listKindStack);
    }

    function listitem_open($level) {
        //$this->doc .= DOKU_TAB.'<listitem level="' . $level . '">';
        $this->doc .= DOKU_LF.str_repeat($this->listKindStack[0], $level) . ' ';
        $this->_openTag($this, 'listitem_close', array());
    }

    function listitem_close() {
        $this->_closeTags($this, __FUNCTION__);
        //$this->doc .= DOKU_LF;
    }

    function listcontent_open() {
        //$this->doc .= '<listcontent>';
        $this->_openTag($this, 'listcontent_close', array());
    }

    function listcontent_close() {
        $this->_closeTags($this, __FUNCTION__);
        //$this->doc .= '</listcontent>';
    }

    function unformatted($text) {
        $this->doc .= '+++';
        $this->doc .= $this->_xmlEntities($text);
        $this->doc .= '+++';
    }

    function unsupportedblock($text, $kind) {
        fwrite(STDERR, "unsupportedblock:'". $kind ."' text: '". var_export($text, TRUE) ."'\n");
        $this->code($text, $kind);
    }

    function php($text) {
        $this->unsupportedblock($text, 'php');
    }

    function phpblock($text) {
        $this->unsupportedblock($text, 'phpblock');
    }

    function html($text) {
        $this->unsupportedblock($text, 'html');
    }

    function htmlblock($text) {
        $this->unsupportedblock($text, 'htmlblock');
    }

    function preformatted($text) {
      if ($this->doc[-1] != DOKU_LF) $this->doc .= DOKU_LF;
      $this->doc .= '....'.DOKU_LF;
      $this->doc .= $text;
      if ($text[-1] != DOKU_LF) $this->doc .= DOKU_LF;
      $this->doc .= '....'.DOKU_LF;
    }

    function quote_open() {
        $this->doc .= '[quote]'.DOKU_LF.'____'.DOKU_LF;
        $this->_openTag($this, 'quote_close', array());
    }

    function quote_close() {
        $this->_closeTags($this, __FUNCTION__);
        if ($this->doc[-1] != DOKU_LF) $this->doc .= DOKU_LF;
        $this->doc .= '____'.DOKU_LF;
    }

    function code($text, $lang = null, $file = null) {
      if ($this->doc[-1] != DOKU_LF) $this->doc .= DOKU_LF;
      $this->doc .= '[source';
      if ($lang != null) $this->doc .= ',' . $lang;
      $this->doc .= ']';
      if ($file != null) $this->doc .= DOKU_LF.'.' . $file;
      $this->doc .= DOKU_LF.'----'.DOKU_LF;
      $this->doc .= $text;
      if ($text[-1] != DOKU_LF) $this->doc .= DOKU_LF;
      $this->doc .= '----'.DOKU_LF;
    }

    function file($text, $lang = null, $file = null) {
      $this->code($text, $lang, $file);
    }

    function acronym($acronym) {
      // not supported by asciidoc 1.5 (maybe in 1.7) => use footnote as workaround)
      $this->doc .= '+++<abbr title="'. $this->_xmlEntities($this->acronyms[$acronym]) .'">' . $this->_xmlEntities($acronym) . '</abbr>+++';
    }

    function smiley($smiley) {
        $this->doc .= 'emoji:' . $this->smiley_to_emoticon[$smiley];
    }

    function entity($entity) {
        $this->doc .= $this->_xmlEntities($this->entities[$entity]);
    }

    /**
     * Multiply entities are of the form: 640x480 where $x=640 and $y=480
     *
     * @param string $x The left hand operand
     * @param string $y The rigth hand operand
     */
    function multiplyentity($x, $y) {
        // $this->doc .= '<multiplyentity>';
        // $this->doc .= '<x>'.$this->_xmlEntities($x).'</x>';
        // $this->doc .= '<y>'.$this->_xmlEntities($y).'</y>';
        // $this->doc .= '</multiplyentity>';
        $this->doc .= $this->_xmlEntities($x).'x'.$this->_xmlEntities($y);
    }

    function singlequoteopening() {
        global $lang;
        $this->doc .= $lang['singlequoteopening'];
        $this->_openTag($this, 'singlequoteclosing', array());
    }

    function singlequoteclosing() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= $lang['singlequoteclosing'];
    }

    function apostrophe() {
        global $lang;
        $this->doc .= $lang['apostrophe'];
    }

    function doublequoteopening() {
        global $lang;
        $this->doc .= $lang['doublequoteopening'];
        $this->_openTag($this, 'doublequoteclosing', array());
    }

    function doublequoteclosing() {
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= $lang['doublequoteclosing'];
    }

    /**
     * Links in CamelCase format.
     *
     * @param string $link Link text
     */
    function camelcaselink($link) {
        $this->internallink($link, $link, 'camelcase');
    }

    function locallink($hash, $name = null) {
        $this->doc .= '<<' .$hash . ',' . $this->_getLinkTitle($name, $hash, $isImage) .'>>';
    }

    /**
     * Links of the form 'wiki:syntax', where $title is either a string or (for
     * media links) an array.
     *
     * @param string $link The link text
     * @param mixed $title Title text (array for media links)
     * @param string $type overwrite the type (for camelcaselink)
     */
    function internallink($link, $title = null, $type='internal') {
        global $ID;
        $id = $link;
        $name = $title;
        list($id, $hash) = explode('#', $id, 2);
        list($id, $search) = explode('?', $id, 2);
        if ($id === '') $id = $ID;
        $default = $this->_simpleTitle($id);
        resolve_pageid(getNS($ID), $id, $exists);
        $name = $this->_getLinkTitle($name, $default, $isImage, $id, 'content');
        $path = str_replace(':', '/', $id);
        //$this->doc .= '<link type="'.$type.'" link="'.$this->_xmlEntities($link).'" id="'.$id.'" search="'.$this->_xmlEntities($search).'" hash="'.$this->_xmlEntities($hash).'">';
        //$this->doc .= $name;
        //$this->doc .= '</link>';
        $this->doc .= '<<'. $path . '#' . $hash .','. $name .'>>';
    }

    /**
     * Full URL links with scheme. $title could be an array, for media links.
     *
     * @param string $link The link text
     * @param mixed $title Title text (array for media links)
     */
    function externallink($link, $title = null) {
        $this->doc .= 'link:' . $link  .'['. $this->_getLinkTitle($title, $link, $isImage) . ']';
    }

    /**
     * @param string $link the original link - probably not much use
     * @param string $title
     * @param string $wikiName an indentifier for the wiki
     * @param string $wikiUri the URL fragment to append to some known URL
     */
    function interwikilink($link, $title = null, $wikiName, $wikiUri) {
        $name = $this->_getLinkTitle($title, $wikiUri, $isImage);
        $url = $this->_resolveInterWiki($wikiName, $wikiUri);
        //$this->doc .= '<link type="interwiki" link="'.$this->_xmlEntities($link).'" href="'.$url.'">';
        //$this->doc .= $name;
        //$this->doc .= '</link>';
        $this->doc .= 'link:' . $url  .'['. $name . ']';
    }

    /**
     * Link to a Windows share, $title could be an array (media)
     *
     * @param string $link
     * @param mixed $title
     */
    function windowssharelink($link, $title = null) {
        $name = $this->_getLinkTitle($title, $link, $isImage);
        //$url = str_replace('\\','/',$link);
        //$url = 'file:///'.$url;
        $this->doc .= 'link:' . $link  .'['. $name . ']';
    }

    function emaillink($address, $name = null) {
        $name = $this->_getLinkTitle($name, '', $isImage);
        $url = $address;
        $url = obfuscate($url);
        $url   = 'mailto:'.$url;
        $this->doc .= $url . '['. $name . ']';
    }

    /**
     * Render media that is internal to the wiki.
     *
     * @param string $src
     * @param string $title
     * @param string $align
     * @param string $width
     * @param string $height
     * @param string $cache
     * @param string $linking
     */
    function internalmedia ($src, $title=null, $align=null, $width=null, $height=null, $cache=null, $linking=null) {
        $this->doc .= $this->_media('internalmedia', $src, $title, $align, $width, $height, $cache, $linking);
    }

    /**
     * Render media that is external to the wiki.
     *
     * @param string $src
     * @param string $title
     * @param string $align
     * @param string $width
     * @param string $height
     * @param string $cache
     * @param string $linking
     */
    function externalmedia ($src, $title=null, $align=null, $width=null, $height=null, $cache=null, $linking=null) {
        $this->doc .= $this->_media('externalmedia', $src, $title, $align, $width, $height, $cache, $linking);
    }

    function table_open($maxcols = null, $numrows = null){
        //$this->doc .= '<table maxcols="' . $maxcols . '" numrows="' . $numrows . '">'.DOKU_LF;
        $this->doc .= '[cols="'. $maxcols . '", options="header"]' . DOKU_LF . '|===' . DOKU_LF;
        $this->_openTag($this, 'table_close', array());
    }

    function table_close(){
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= DOKU_LF. '|===' . DOKU_LF;
    }

    function tablerow_open(){
        $this->doc .= DOKU_LF;
        $this->_openTag($this, 'tablerow_close', array());
    }

    function tablerow_close(){
        $this->_closeTags($this, __FUNCTION__);
        //$this->doc .= '</tablerow>'.DOKU_LF;
    }

    function tableheader_open($colspan = 1, $align = null, $rowspan = 1){
      $this->tablecell_open($colspan, $align, $rowspan);
      // TODO
        // $this->doc .= '<tableheader';
        // if ($colspan>1) $this->doc .= ' colspan="' . $colspan . '"';
        // if ($rowspan>1) $this->doc .= ' rowspan="' . $rowspan . '"';
        // if ($align) $this->doc .= ' align="' . $align . '"';
        // $this->doc .= '>';
        // $this->_openTag($this, 'tableheader_close', array());
    }

    function tableheader_close(){
      $this->tablecell_close();
        // $this->_closeTags($this, __FUNCTION__);
        // $this->doc .= '</tableheader>';
    }

    function tablecell_open($colspan = 1, $align = null, $rowspan = 1) {
      if ($rowspan > 1) $this->doc .= '.' . $rowspan . '+';
      if ($colspan > 1) $this->doc .= $colspan . '+';
      if ($align == "left") $this->doc .= '<';
      if ($align == "center") $this->doc .= '^';
      if ($align == "right") $this->doc .= '>';
      $this->doc .= 'a|'; //always enable asciidoc content
      $this->_openTag($this, 'tablecell_close', array());
    }

    function tablecell_close(){
        $this->_closeTags($this, __FUNCTION__);
        $this->doc .= DOKU_LF;
    }

    /**
     * Private functions for internal handling
     */
    function _xmlEntities($text){
        return htmlspecialchars($text,ENT_COMPAT,'UTF-8');
    }

    /**
     * Render media elements.
     * @see Doku_Renderer_xhtml::internalmedia()
     *
     * @param string $type Either 'internalmedia' or 'externalmedia'
     * @param string $src
     * @param string $title
     * @param string $align
     * @param string $width
     * @param string $height
     * @param string $cache
     * @param string $linking
     */
    function _media($type, $src, $title=null, $align=null, $width=null, $height=null, $cache=null, $linking = null) {
        global $ID;
        $link = $src;
        list($src, $hash) = explode('#', $src, 2);
        if ($type == 'internalmedia') {
            resolve_mediaid(getNS($ID), $src, $exists);
        }
        $name = $title ? $this->_xmlEntities($title) : $this->_xmlEntities(utf8_basename(noNS($src)));
        if ($type == 'internalmedia') {
        //     $src = ' id="'.$this->_xmlEntities($src).'" hash="'.$this->_xmlEntities($hash).'"';
            $url = str_replace(':', '/', $src);
        } else {
            $url = $src;
        }

        //$out = 'image:' . $url. '['. $name . ',width="'. $width .'",height="'. $height . '",align="' . $align . '", link="'. $linking .'"]';
        if (stripos($url, '.mp4') !== false || stripos($url, '.webm') !== false || stripos($url, '.ogv') !== false) {
          $out = DOKU_LF.'video::' . $url . '[]'.DOKU_LF;
        } else {
          if (empty($align)) {
            $out = 'image:' . $url. '['. $name . ',width="'. $width .'",height="'. $height . '"]';
          } else {
            $out = DOKU_LF.'image::' . $url. '['. $name . ',width="'. $width .'",height="'. $height . '",align="' . $align .'"]'.DOKU_LF;
          }
        }
        //$out .= '<media type="'.$type.'" link="'.$this->_xmlEntities($link).'"'.($src).' align="'.$align.'" width="'.$width.'" height="'.$height.'" cache="'.$cache.'" linking="'.$linking.'">';
        return $out;
    }

    function _getLinkTitle($title, $default, & $isImage, $id=null, $linktype='content'){
        $isImage = false;
        if ( is_array($title) ) {
            $isImage = true;
            return $this->_imageTitle($title);
        } elseif ( is_null($title) || trim($title)=='') {
            if (useHeading($linktype) && $id) {
                $heading = p_get_first_heading($id);
                if ($heading) {
                    return $this->_xmlEntities($heading);
                }
            }
            return $this->_xmlEntities($default);
        } else {
            return $this->_xmlEntities($title);
        }
    }

    function _imageTitle($img) {
        global $ID;

        // some fixes on $img['src']
        // see internalmedia() and externalmedia()
        list($img['src'], $hash) = explode('#', $img['src'], 2);
        if ($img['type'] == 'internalmedia') {
            resolve_mediaid(getNS($ID), $img['src'], $exists);
        }

        return $this->_media($img['type'],
                              $img['src'],
                              $img['title'],
                              $img['align'],
                              $img['width'],
                              $img['height'],
                              $img['cache']);
    }

    function _openTag($class, $func, $data=null) {
        $this->tagStack[] = array($class, $func, $data);
    }

    function _closeTags($class=null, $func=null) {
        if ($this->tagClosing==true) return;  // skip nested calls
        $this->tagClosing = true;
        while(count($this->tagStack)>0) {
            list($lastclass, $lastfunc, $lastdata) = array_pop($this->tagStack);
            if (!($lastclass===$class && $lastfunc==$func)) call_user_func_array( array($lastclass, $lastfunc), $lastdata );
            else break;
        }
        $this->tagClosing = false;
    }
}
