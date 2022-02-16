<?php

    namespace Nowshad;
    
    use Exception;

    # this class will handle how to render pages
    
    class Inflater {
        
        private $template_folder = null;
        private $css_folder = null;
        private $js_folder = null;
        private $static_folder = null;
        private $args = null;
        
        function __construct(){
            
            # initiating variables
            
            $this->template_folder = "templates/";
            $this->css_folder = "static/css/";
            $this->js_folder = "static/js/";
            $this->static_folder = "static/";
            $this->args = [];
            
            # collecting the "css" files from "css" folder
            
            $css_folder = $this->css_folder;
            
            if(file_exists($css_folder) && is_dir($css_folder)){
                $css_files = "";
                foreach (scandir($css_folder) as $v){
                    if(preg_match("/\S+(\.css)/",$v)){
                        $ext_less = str_replace(".css","",$v);
                        $css_files = $css_files."public $".$ext_less."='".$this->root_uri().$css_folder.$v."';";
                        $css_files_obj = null;
                        eval('$css_files_obj = new class{'.$css_files.'};');
                        $this->args["_CSS"] = $css_files_obj;
                    }
                }
            }
            
            # collecting the "js" files from "js" folder
            
            $js_folder = $this->js_folder;
            if(file_exists($js_folder) && is_dir($js_folder)){
                $js_files = "";
                foreach (scandir($js_folder) as $v){
                    if(preg_match("/\S+(.js)/",$v)){
                        $ext_less = str_replace(".js","",$v);
                        $js_files = $js_files."public $".$ext_less."='".$this->root_uri().$js_folder.$v."';";
                        $js_files_obj = null;
                        eval('$js_files_obj = new class{'.$js_files.'};');
                        $this->args["_JS"] = $js_files_obj;
                    }
                }
            }
            
            
            
            # will return the site root url
            $this->args["_ROOT"] = $this->root_uri();
            
            # returns the static folder path if empty param given else returns the combination
            $this->args["static_url"] = function (string $path=""){
                                            $url = $this->root_uri().$this->static_folder."/".preg_replace("/[\/]{2,}/","/",$path);
                                            return $url;
                                        };
            

            
        }
        
        private function root_uri(){
            $scheme = $_SERVER["REQUEST_SCHEME"];
            $host = $_SERVER["HTTP_HOST"];
            $url = $host."/";
            $proto = (empty($proto)) ? "http:#":$proto.":#";
            $uri = $proto.preg_replace("/(\/){2,}/","/",$url);
            return $uri;
        }
        
        
        # to pass data as "php data" like "$variable"
        function parse_args(array $args = array(), bool $escape = true){
            foreach ($args as $k => $v){
                $v = ($escape && !is_array($v) && gettype($v) != "object") ? htmlspecialchars($v):$v;
                $this->args[$k] = $v;
            }
        }
        
        
        # to flush text messages to the page as
        function flush(string $msg){
            $this->args["flushes"][] = $msg;
            $this->args["is_flushed"] = (count($this->args["flushes"])>0) ? true:false;
        }
        
        
        # to render the given page
        function inflate(string $file_name, array $args = [], bool $escape = false) { 
            
            # parsing the arguments sent to the template
            $this->parse_args($args, $escape);
            
            $file_path = $this->template_folder."/".$file_name.".html";
            $file_html = preg_replace("/(\/{2,}|\s)/","/",$file_path);
            $file_htm = str_replace(".html",".htm",$file_html);
            
            if(file_exists($file_html) || file_exists($file_htm)){
                
                # extract all array keys to php variable
                extract($this->args);
                
                $file = (file_exists($file_html)) ? $file_html:$file_htm;
                
                # get the contents from the given file
                $html = file_get_contents($file);
                
                
                # check for the existence of "extends" keyword
                if(preg_match("/({%\s*extends\s*(\"|').*(\"|')\s*%})/",$html,$extends)){
                    
                    $extended_file = $this->template_folder.preg_replace("/({%\s*extends\s*(\"|'))|(\s*\"\s*%})/","",$extends[0]);
                    if(file_exists($extended_file)){
                        
                        $template = file_get_contents($extended_file);
                        
                        # to check if the "block-endblock" exists in the template file
                        if(preg_match("/({%\s*block\s*%})[\t\n\v\s\S\w\W]*({%\s*endblock\s*%})/",$template,$parent_block)){
                            $child_block = preg_replace("/({%\s*extends\s*(\"|').*(\"|')\s*%})/","",$html);
                            $html = preg_replace("/({%\s*block\s*%})[\t\n\v\s\S\w\W]*({%\s*endblock\s*%})/",$child_block,$template);
                        }else{
                            die("No {% block %} {% endblock %} found in the template file!");
                        }
                    }else{
                        die("Template File - $extended_file not found!");
                    }
                }
                
                
                # removing {% block %} {% endblock %}
                
                $html_ = preg_replace("/({%\s*block\s*%})|({%\s*endblock\s*%})/m","",$html);
                
                
                # removing all single quotes
                $html_ = preg_replace("/='(?=\w*)/",'="',$html_);
                $html_ = preg_replace("/(?<=(\w|\s))'(?=[^)\s])/",'"',$html_);
                
                # covering all the html using single quotes to treat as php code
                $html_ = "echo '$html_';";
                
                # this will treat "{{ ... }}" as "echo ...;"
                $html_ = str_replace("{{","';echo ",str_replace("}}",";echo '",$html_));
                
                # replacing the logics to php code to make them executable
                $html_ = preg_replace("/({%)\s*if\s*/","';if(",$html_);
                $html_ = preg_replace("/({%)\s*foreach\s*/","'; foreach(",$html_);
                $html_ = preg_replace("/({%)\s*for\s*/","'; for(",$html_);
                $html_ = preg_replace("/({%)\s*while\s*/","'; while(",$html_);
                $html_ = preg_replace("/({%)\s*endforeach\s*(%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%)\s*endfor\s*(%})/","';} echo '",$html_);
                $html_ = preg_replace("/({%)\s*endwhile\s*(%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%)\s*endif\s*(%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%\s*end[^%]*\s*%})/","'; } echo '",$html_);
                $html_ = preg_replace("/({%)\s*elif\s*/","';}else if(",$html_);
                $html_ = preg_replace("/:\s*(%})/","){echo '",$html_);
                $html_ = preg_replace("/({%)/","'; ",$html_);
                $html_ = preg_replace("/(%})/",";echo '",$html_);
                
                
                # removing all "{{ ... }"
                $html_ = preg_replace("/echo\s*[^}']*\}/","echo '",$html_);
                
                # removing all "{ ... }}"
                $html_ = preg_replace("/(?<=[^)])\s*\{\s*[^;]*;/","';",$html_);
                
                $html_ = preg_replace(
                    "/(\[\s*')/", 
                    "[\"", 
                    preg_replace(
                        "/\(\s*'/", 
                        "(\"", 
                        preg_replace(
                            "/'\s*\)/", 
                            "\")", 
                            preg_replace(
                                "/'\s*\]/", 
                                "\"]", 
                                $html_
                            )
                        )
                    )
                );
                
                # executing all the codes as php script
                eval($html_);
                
            }else{
                throw new Exception("File $file_path not found!");
            }
        }
        
        
        
        
    }

?>