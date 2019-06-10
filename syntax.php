<?php
     
    // must be run within Dokuwiki
    if(!defined('DOKU_INC')) die();
     
    /**
     * All DokuWiki plugins to extend the parser/rendering mechanism
     * need to inherit from this class
     */
    class syntax_plugin_JSONtoEFIT extends DokuWiki_Syntax_Plugin {
     
        public function getType(){ return 'formatting'; }
        public function getAllowedTypes() { return array('formatting', 'substition', 'disabled'); }   
        public function getSort(){ return 158; }
        public function connectTo($mode) { $this->Lexer->addEntryPattern('<efit>.*?(?=</efit>)',$mode,'plugin_JSONtoEFIT'); }
        public function postConnect() { $this->Lexer->addExitPattern('</efit>','plugin_JSONtoEFIT'); }
     
        
        public function convertSection($data, $writeOutQuantities=true) {

            $result = "";
         
            // if data is undefined or null or an empty array, then we skip this section and return the empty string
         
            if ($data) {
         
                foreach ($data as $el) {
         
                    if ($writeOutQuantities) {
         
                        for ($i = 0; $i < $el->quantity; $i++){
         
                            $result .= $el->name . "\n";
         
                        }
         
                    } else {
         
                        $result .= $el->name . " x" . $el->quantity ."\n";
         
                    }
         
                }
         
            }
         
            return $result;
         
         }

         public function convert($data){
            
            $fitJSONDecoded = $data;

            //Fit Name
            $stFitname = $fitJSONDecoded->fitting_name;
            //Fit Hull
            $stHull = $fitJSONDecoded->hull;

            //Fit header
            $stHeader = "[" . $stHull . ", " . $stFitname . "]";
            
            // convert each section to a string that we can later merge together

            $low = $this->convertSection($fitJSONDecoded->low);

            $med = $this->convertSection($fitJSONDecoded->med);
            
            $high = $this->convertSection($fitJSONDecoded->high);

            $rigs = $this->convertSection($fitJSONDecoded->rigs);

            $drones = $this->convertSection($fitJSONDecoded->drones, false);

            $ammo = $this->convertSection($fitJSONDecoded->ammo, false);
    
            $efit = join("\r\n\r\n", [$stHeader, $low, $med, $high, $rigs, $drones, $ammo]);

            return $efit;

         }



        /**
         * Handle the match
         */
        public function handle($match, $state, $pos, Doku_Handler $handler){
            switch ($state) {
              case DOKU_LEXER_ENTER :

                //Get Doctrine name from between tags               
                list($efit, $fit) = preg_split("(<efit>)",$match);

                //Request for doctrine from website (Patriot test)
                
                    $Fitfinal = "";
                    //$baseurl = "https://b9wsp01mkc.execute-api.us-east-1.amazonaws.com/dev/fittings/";
                    $baseurl = $this->getConf('BASEURL');

                    error_log($baseurl);

                    $url = $baseurl . $fit;
                    $fitJSON = @file_get_contents($url);
                    //Error handling for bad fit name
                    if (strpos($http_response_header[0], "200")){
                        $fitJSONDecoded = json_decode($fitJSON);
                        $Fitfinal = $this->convert($fitJSONDecoded);
                    } else {
                        error_log("Failed JSON GET");
                        //Prepare error message to display on screen through renderer.

                    }
               
                //error_log($fitJSONDecoded->fitting_name);

                return array($state, $Fitfinal);
    
              case DOKU_LEXER_UNMATCHED :  return array($state, $match);
              case DOKU_LEXER_EXIT :       return array($state, '');
            }
            return array();
        }
     
        /**
         * Create output
         */
        public function render($mode, Doku_Renderer $renderer, $data) {
            // $data is what the function handle() return'ed.
            if($mode == 'xhtml'){
                /** @var Doku_Renderer_xhtml $renderer */
                list($state,$match) = $data;
                switch ($state) {
                    case DOKU_LEXER_ENTER :      
                        
                        $renderer->code($match);

                        //$renderer->doc .= $match;
                        break;
     
                    case DOKU_LEXER_UNMATCHED :  
                        $renderer->doc .= $renderer->_xmlEntities($match); 
                        break;
                    case DOKU_LEXER_EXIT :       
                        $renderer->doc .= ""; 
                        break;
                }
                return true;
            }
            return false;
        }
     
    }

