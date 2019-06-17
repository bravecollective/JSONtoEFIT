<?php
     
	 /**
	 * JSON to EFIT Plugin for eve online
	 * Takes JSON formated fits and converts them to EFIT syntax.
	 * Made for Brave Newbies.
	 */
	 
	 
    // must be run within Dokuwiki
    if(!defined('DOKU_INC')) die();
     
    /**
     * All DokuWiki plugins to extend the parser/rendering mechanism
     * need to inherit from this class
     */
    class syntax_plugin_JSONtoEFIT extends DokuWiki_Syntax_Plugin {
     
        public function getType(){ return 'substition'; }
        public function getAllowedTypes() { return array(); }   
        public function getSort(){ return 158; }
        public function connectTo($mode) { $this->Lexer->addEntryPattern('<efit>.*?(?=</efit>)',$mode,'plugin_JSONtoEFIT'); }
        public function postConnect() { $this->Lexer->addExitPattern('</efit>','plugin_JSONtoEFIT'); }
     
        /**
		 * Takes JSON formatted data from one slot (e.g. low). Provides different output depending on if it should be displayed line by line or as "x500 Mjolnir Navy issue..."
		 * $writeOutQuantities (Default true) true means write as xQuantity (for ammo) false means write line by line (for modules)	
		 */
        public function convertSection($data, $writeOutQuantities=true) {

            $result = "";
         
            // if data is undefined or null or an empty array, then we skip this section and return the empty string
         
            if ($data) {
         
                foreach ($data as $el) {
					//Write out as "xQuantity"
                    if ($writeOutQuantities) {
         
                        for ($i = 0; $i < $el->quantity; $i++){
         
                            $result .= $el->name . "\n";
         
                        }
					//Write out line by line
                    } else {
         
                        $result .= $el->name . " x" . $el->quantity ."\n";
         
                    }
         
                }
         
            }
         
            return $result;
         
         }

		 /**
		 * Takes JSON formatted data from API. 
		 * Takes input from convertSection function and prepares a string formatted in Efit style
		 */
         public function convert($data){
            
            //Cancel if in comments
			if (isset($_REQUEST['comment'])) {
				return false;
			}
			
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

            $charges = $this->convertSection($fitJSONDecoded->ammo, false);
			
			// Take above information and convert to efit
            $efit = join("\r\n\r\n", [$stHeader, $low, $med, $high, $rigs, $drones, $charges]);

            return $efit;

         }



        /**
         * Handle the match
         */
        public function handle($match, $state, $pos, Doku_Handler $handler){
            switch ($state) {
              case DOKU_LEXER_ENTER :

                //Get Doctrine name from between tags in order to pass to search for fit.              
                list($efit, $fit) = preg_split("(<efit>)",$match);

                //Request for doctrine from website using base URL provided in config               
                    
					$Fitfinal = "";
					$fetcherror= "";
                    $baseurl = $this->getConf('BASEURL');
					
					//Check baseurl for problems (baseurl should be http(s) due to regex in config settings, but safety first.
					if ($baseurl == "") {
						$fetcherror = "Empty url, please check configuration";
					}
									
                    error_log($baseurl);
					//Merge base url and fit name
					$fitunicode = rawurlencode($fit);
                    $url = $baseurl . $fitunicode;
					$fitJSON = @file_get_contents($url);
                    //Error handling for bad fit name
                    if (strpos($http_response_header[0], "200")){
                        $fitJSONDecoded = json_decode($fitJSON);
                        $Fitfinal = $this->convert($fitJSONDecoded);
                    } else {
                        error_log("Failed JSON GET");
                        //Prepare error message to display on screen through renderer.
                        //User friendly errors for potential errors
                        if (strpos($http_response_header[0], "404")){
                        $fetcherror = "problem getting fit. Reason: Fit not found ";
                        }
                    }
               
                //error_log($fitJSONDecoded->fitting_name);

                return array($state, $Fitfinal, $fetcherror);
    
              case DOKU_LEXER_UNMATCHED :  return array($state, $match);
              case DOKU_LEXER_EXIT :       return array($state, '');
            }
            return array();
        }
     
        /**
         * Create output
         */
        public function render($mode, Doku_Renderer $renderer, $data) {
            // $data is what the function handle() returned.
            if($mode == 'xhtml'){
                /** @var Doku_Renderer_xhtml $renderer */
                list($state,$match,$fetcherror) = $data;
                switch ($state) {
                    case DOKU_LEXER_ENTER :
						//Display error instead of efit if error occurs.
                        if(!empty($fetcherror)){
							$renderer->code($fetcherror);
						}else{
							$renderer->code(htmlspecialchars($match));
		
                        //$renderer->doc .= $match;
							break;
						}
     
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
