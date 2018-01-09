<?php
use MatthiasMullie\Minify;
class view {
    private static $_fs_css;
    private static $_css;
    private static $_css_index;
    private static $_em_js;
    private static $_js;
    private static $_js_index;
    private static $_view;
    private static $_rawView;
    private static $_template = "default";
    private static $_data = array();
    static function getPageView($controllerArray) {
        $page_string = $controllerArray["controller"];
        if (count($controllerArray["param"]) > 0) {
            foreach ($controllerArray["param"] as $key => $val) {
                $page_string.= "&".$key."=".$val;
            }
        }
//        echo "<div>".$page_string."</div>";
        $page_hash = md5($page_string);
        $page_cache = cache::getCache($page_hash);
        if ($page_cache == "") {
//            echo "no cache page";
            $page_cache = self::genPage($controllerArray);
        }
        self::$_view = $page_cache;
        echo self::$_rawView;
    }
    static  function  getTemplateName() {
        return self::$_template;
    }
    static function sessionView() {

    }
    static function genPage($controllerArray) {
        $htmlFileCheck = false;
        $controllerFileCheck = false;
        $templateUsingCheck = false;
        $html_file = BASE_DIR."/view/".$controllerArray["controller"].".html";
        if (file_exists($html_file)) {
            $htmlFileCheck = true;
        }
        $controller_file = BASE_DIR."/controller/".$controllerArray["controller"]."Controller.php";
        if (file_exists($controller_file)) {
            $controllerFileCheck = true;
        }
        if ($htmlFileCheck == true) {
            self::addView($controllerArray["controller"]);
            if (!preg_match("/<html[ a-z='\"-_]*>/m",self::$_data["<{view}>"])) {
                $templateUsingCheck = true;
            } else {
                self::$_template = "";
            }
        }
        if ($htmlFileCheck == false && $controllerFileCheck == false) {
            pgnUtil::showMsg("File not found: ".$controllerArray["controller"].".html or ".$controllerArray["controller"]."Controller.php");
        }
        if ($controllerFileCheck == true) {
            if ($htmlFileCheck == false) {
                self::$_template  = "";
            }
            if (file_exists(BASE_DIR."/controller/globalController.php")) {
                include_once BASE_DIR."/controller/globalController.php";
            }
            include_once $controller_file;
        }
        if ($templateUsingCheck == true && self::$_template != "") {
            $out = ob_get_clean();
            if (empty(self::$_data["<{view}>"])) {
                self::$_data["<{view}>"] = "";
            }
            self::$_data["<{view}>"].=$out;
            self::dataRegister("header",file_get_contents(BASE_DIR."/view/template/".self::$_template."/header.html"));
            self::dataRegister("footer", file_get_contents(BASE_DIR."/view/template/".self::$_template."/footer.html"));
            self::dataRegister("metaTag", file_get_contents(BASE_DIR."/view/template/".self::$_template."/meta.html"));
            if (empty(self::$_data["<{title}>"]) && defined("DEFAULT_TITLE")) {
                self::dataRegister("title",DEFAULT_TITLE);
            }
            $fs_css_data = "";
            if (is_array(self::$_fs_css) && count(self::$_fs_css) > 0) {
                foreach(self::$_fs_css as $key => $val) {
                    $fs_css_data .= file_get_contents(BASE_DIR."/".CSS_PATH."/".$val.".css")."\r\n";
                }
                if (strlen($fs_css_data) > 0) {
                    $minifierCss = new Minify\CSS();
                    $minifierCss->add($fs_css_data);
                    $fs_css_data = "<style ".(ENV_MODE == "dev" ? " class='devCss' fileList='".implode(",",self::$_fs_css)."'":"").">".$minifierCss->minify()."</style>";
                }
            }
            self::dataRegister("firstSignCss",$fs_css_data);
            $core_js = file_get_contents(BASE_DIR."/systems/js/cssPreload.js");
            $core_js .= "\n".file_get_contents(BASE_DIR."/systems/js/jsPreload.js");
            $minifierCoreJs = new Minify\JS();
            $minifierCoreJs->add($core_js);
            $em_js_data_all = $minifierCoreJs->minify();
            if (is_array(self::$_em_js) && count(self::$_em_js ) > 0) {
                foreach(self::$_em_js as $key => $val) {
                    $em_js_data = file_get_contents(BASE_DIR . "/" . JS_PATH . "/" . $val . ".js");
                    if (!preg_match("/\.min\./", $val)) {
                        $minifierJs = new Minify\JS();
                        $minifierJs->add($em_js_data);
                        $em_js_data_all .=  $minifierJs->minify();
                    }
                }
            }
            if (strlen($em_js_data_all) > 0) {
                $em_js_data_all = "<script language=JavaScript>".$em_js_data_all."</script>";
            }
            self::dataRegister("embedJS",$em_js_data_all);
            self::$_rawView  =  file_get_contents(BASE_DIR."/view/template/".self::$_template."/master.html");
            $css_resource = resource::registerResourceHash(self::$_css,"css");
            $js_resource = resource::registerResourceHash(self::$_js,"js");
            cache::saveResource();
            $uxControlJs = "";
            if (strlen($css_resource) > 0) {
                if (ENV_MODE == "dev") {
                    $uxControlJs = "<style class='devCss' fileList=".implode(",",self::$_css)."></style>";
                    $uxControlJs .= " <script language=JavaScript>loadJs('js/".$js_resource.".js');</script>";
                } else {
                    $uxControlJs = " <script language=JavaScript>loadCss('css/".$css_resource.".css'".(strlen($js_resource) > 0 ? ",loadJs('js/".$js_resource.".js')" : "").");</script>";
                }
            }
            self::dataRegister("systemUXControl",$uxControlJs);
            self::dataReplace();
        } elseif ($htmlFileCheck == true) {
            self::$_rawView  =  file_get_contents($html_file);
        }
    }
    static function dataReplace() {
        $search = array();
        $replace = array();
        foreach (self::$_data as $key => $val) {
            $search[] = $key;
            $replace[] = $val;
        }
        self::$_rawView = str_replace($search,$replace,self::$_rawView);
    }
    static function clearView() {
        if (isset(self::$_data["<{view}>"])) {
            unset(self::$_data["<{view}>"]);
        }
    }
    static function addView($fileName) {
        if (file_exists(BASE_DIR."/view/".$fileName.".html")) {
            if (!is_array(self::$_data)) {
                self::$_data = array();
            }
            $currentViewData = "";
            if (isset(self::$_data["<{view}>"])) {
                $currentViewData = self::$_data["<{view}>"];
            }
            $currentViewData.= file_get_contents(BASE_DIR."/view/".$fileName.".html");
            self::dataRegister("view",$currentViewData);
        } else {
            pgnUtil::showMsg("File Missing: view/".$fileName.".html");
        }
    }
    static function dataRegister($key,$data) {
        if (!is_array(self::$_data)) {
            self::$_data = array();
        }
        self::$_data["<{".$key."}>"] = $data;
    }
    static function setTemplate($template) {
        if (is_dir(BASE_DIR."/view/template/".$template)
            && file_exists(BASE_DIR."/view/template/".$template."/master.html")
            && file_exists(BASE_DIR."/view/template/".$template."/header.html")
            && file_exists(BASE_DIR."/view/template/".$template."/footer.html")
            && file_exists(BASE_DIR."/view/template/".$template."/meta.html")) {
            self::$_template = $template;
        } else {
            pgnUtil::showMsg("Template Missing: ".$template);
        }
    }
    static function addFirstSignStyleSheet($css_file_name) {
        if ($css_file_name != "") {
            $css_array = explode(",",$css_file_name);
            foreach ($css_array as $val) {
                $file_path = BASE_DIR."/".CSS_PATH."/".$val.".css";
                if (file_exists($file_path) && empty(self::$_css_index[$val])) {
                    self::$_css_index[$val] = 1;
                    self::$_fs_css[] = $val;
                } else {
                    if (!file_exists($file_path)) {
                        pgnUtil::showMsg("CSS File not found: ".$val);
                    }
                }
            }
        }
    }
    static function addStyleSheet($css_file_name) {
        if ($css_file_name != "") {
            $css_array = explode(",",$css_file_name);
            foreach ($css_array as $val) {
                $file_path = BASE_DIR."/".CSS_PATH."/".$val.".css";
                if (file_exists($file_path) && empty(self::$_css_index[$val])) {
                    self::$_css_index[$val] = 1;
                    self::$_css[] = $val;
                } else {
                    if (!file_exists($file_path)) {
                        pgnUtil::showMsg("CSS File not found: ".$val);
                    }
                }
            }
        }
    }
    static function addEmbedJavascript($js_file_name) {
        if ($js_file_name != "") {
            $js_array  = explode(",",$js_file_name);
            foreach ($js_array as $val) {
                $file_path = BASE_DIR."/".JS_PATH."/".$val.".js";
                if (file_exists($file_path) && empty(self::$_js_index[$val])) {
                    self::$_js_index[$val] = 1;
                    self::$_em_js[] = $val;
                } else {
                    if (!file_exists($file_path)) {
                        pgnUtil::showMsg("JS File not found: ".$val);
                    }
                }
            }
        }
    }
    static function addJavascript($js_file_name) {
        if ($js_file_name != "") {
            $js_array  = explode(",",$js_file_name);
            foreach ($js_array as $val) {
                $file_path = BASE_DIR."/".JS_PATH."/".$val.".js";
                if (file_exists($file_path) && empty(self::$_js_index[$val])) {
                    self::$_js_index[$val] = 1;
                    self::$_js[] = $val;
                } else {
                    if (!file_exists($file_path)) {
                        pgnUtil::showMsg("JS File not found: ".$val);
                    }
                }
            }
        }
    }
}