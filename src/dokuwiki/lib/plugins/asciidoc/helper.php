<?php
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class helper_plugin_asciidoc extends DokuWiki_Plugin {

    /**
     * Hooks for handling other plugins
     *
     * Since we generally outputs xml-ized instructions,
     * we don't really need the plugins themselves to manage them.
     *
     * Feel free to add or modify hooks for plugins below to match your need.
     */
    function _asciidoc_extension(&$renderer,$name,$data) {
        switch ($name) {
          /*
            case 'htmlcomment':
                list($state, $match) = $data;
                $renderer->doc .= '<!--';
                if (HTMLCOMMENT_SAFE) {
                    $renderer->doc .= $renderer->_xmlEntities($match);
                } else {
                    $renderer->doc .= $match;
                }
                $renderer->doc .= '-->';
                return true;
            case 'info':
                $renderer->doc .= '<macro name="info" type="'.$data[0].'" />'.DOKU_LF;
                return true;
            case 'pageredirect':
                list($page, $message) = $data;
                $renderer->doc .= '<macro name="pageredirect" target="'.$page.'" />'.DOKU_LF;
                return true;
            case 'plaintext':
                $renderer->doc .= '<plaintext>';
                $renderer->doc .= $renderer->_xmlEntities($data);
                $renderer->doc .= '</plaintext>';
                return true;
            case 'tag_topic':
                list($ns, $tag, $flags) = $data;
                $renderer->doc .= '<topic namespace="'.$ns.'" tags="'.$tag.'" flags="'.implode(' ',$flags).'" />'.DOKU_LF;
                return true;
              */
            case 'tag_tag':
                foreach ($data as $tag) {
                    array_push($renderer->keywords, $renderer->_xmlEntities($tag));
                }
                return true;
            case 'iframe':
                $str = 'iframe::'.$data['url'].'[width="'. $data['width'].'", height="'. $data['height'].'", alt="'. $data['alt'].'", scroll="'. var_export($data['scroll'], true).'",border="'. var_export($data['border'], true).'",align="'. var_export($data['align'], true).'"]'.DOKU_LF;
                $renderer->doc .= $str;
                return true;
            case 'note':
                list($kind, $m1, $m2) = $data;
                switch ($kind) {
                  case '1':
                    switch ($m1) {
                      case 'notewarning': 
                        $renderer->doc .= DOKU_LF."[WARNING]";
                        break;
                      case 'notetip': 
                        $renderer->doc .= DOKU_LF."[TIP]";
                        break;
                      case 'noteimportant': 
                        $renderer->doc .= DOKU_LF."[IMPORTANT]";
                        break;
                      case 'noteclassic': 
                        $renderer->doc .= DOKU_LF."[NOTE]";
                        break;
                      default:
                        fwrite(STDERR, "note ,'". $m1 ."''\n");
                        $renderer->doc .= DOKU_LF."[NOTE]";
                    }
                    $renderer->doc .= DOKU_LF."====".DOKU_LF;
                    return true;
                  case '3':
                    $renderer->doc .= $m1;//$renderer->_xmlEntities($m1);
                    return true;
                  case '4':
                    $renderer->doc .= DOKU_LF."====".DOKU_LF;
                    return true;
                }
                return true;
        }
        fwrite(STDERR, "case ,'". $name ."' data: '". var_export($data, TRUE) ."'\n");
        return false;
    }
}
