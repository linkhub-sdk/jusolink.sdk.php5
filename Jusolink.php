<?php
/**
* =====================================================================================
* Class for base module for Jusolink API SDK. It include base functionality for
* RESTful web service request and parse json result. It uses Linkhub module
* to accomplish authentication APIs.
*
* This module uses curl and openssl for HTTPS Request. So related modules must
* be installed and enabled.
*
* http://www.linkhub.co.kr
* Author : Jeong Yoo han (yhjeong@linkhub.co.kr)
* Written : 2015-05-14
*
* Thanks for your interest.
* We welcome any suggestions, feedbacks, blames or anythings.
* ======================================================================================
*/

require_once 'Linkhub/linkhub.auth.php';

class Jusolink
{	
	const ServiceID = 'JUSOLINK';
	const ServiceURL = 'https://juso.linkhub.co.kr';
    const Version = '1.0';
    
    private $Linkhub;
	private $token;
		    
    public function __construct($LinkID,$SecretKey) {
    	$this->Linkhub = Linkhub::getInstance($LinkID,$SecretKey);
    	$this->scopes[] = '200';
    }

    private function getsession_Token($ForwardIP) {
		$Refresh = true;

		if(!is_null($this->token)) {
            $Expiration = new DateTime($this->token->expiration,new DateTimeZone("UTC"));
            $now = gmdate("Y-m-d H:i:s",time());
            $Refresh = $Expiration < $now;
    	}

    	if($Refresh) {
    		try
    		{
    			$this->token = $this->Linkhub->getToken(Jusolink::ServiceID,null,$this->scopes,$ForwardIP);
    		}catch(LinkhubException $le) {
    			throw new JusoLinkException($le->getMessage(),$le->getCode());
    		}
        }
    	return $this->token->session_token;
    }
         
    // 회원 잔여포인트 확인
    public function GetBalance() {
    	try {
    		return $this->Linkhub->getPartnerBalance($this->getsession_Token(null),Jusolink::ServiceID);
    	}catch(LinkhubException $le) {
    		throw new JusoLinkException($le->message,$le->code);
    	}
    }

	// 검색단가 확인
	public function GetUnitCost(){
		try{
			return $this->executeCURL('/Search/UnitCost')->unitCost;
		}catch(LinkhubException $le){
			throw new JusoLinkException($le->message,$le->code);
		}
	}

	// 주소 검색
	public function search($IndexWord, $Page, $PerPage = null, $noSuggest = false, $noDiff = false){
		if(!is_null($Page) && $Page <1) $Page = null;

		if($PerPage != null){
			if($PerPage < 0) $PerPage = 20;
		}

		$url = '/Search';

		if(is_null($IndexWord) || $IndexWord === ""){
			throw new JusoLinkException(-99999999, '검색어가 입력되지 않았습니다.');
		}
		
		$url = $url.'?Searches='.urlencode($IndexWord);


		if(!is_null($Page)){
			$url = $url.'&PageNum='.$Page;
		}

		if(!is_null($PerPage)){
			$url = $url.'&PerPage='.$PerPage;
		}

		if(!is_null($noSuggest) && $noSuggest){
			$url = $url.'&noSuggest=true';
		}	

		if(!is_null($noDiff) && $noDiff){
			$url = $url.'&noDifferential=true';
		}
		
		$result = $this->executeCURL($url);

		$SearchObj = new SearchResult();
		$SearchObj->fromJsonInfo($result);
		
		return $SearchObj;

	}
         
    protected function executeCURL($uri,$CorpNum = null,$userID = null,$isPost = false, $action = null, $postdata = null,$isMultiPart=false) {
		$http = curl_init((Jusolink::ServiceURL).$uri);
		$header = array();

		$header[] = 'Authorization: Bearer '.$this->getsession_Token(null);
		$header[] = 'x-api-version: '.Jusolink::Version;

		curl_setopt($http, CURLOPT_HTTPHEADER,$header);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
		
		$responseJson = curl_exec($http);
		$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);
		
		curl_close($http);

		if($http_status != 200) {
			throw new JusoLinkException($responseJson);
		}
		return json_decode($responseJson);
	}
}

class SearchResult
{
	public $searches;
	public $deletedWord;
	public $suggest;
	public $sidoCount;
	public $numFound;
	public $listSize;
	public $totalPage;
	public $page;
	public $chargeYN;
	public $juso;
	
	public function fromJsonInfo($jsonInfo){
		isset($jsonInfo->searches) ? ($this->searches = $jsonInfo->searches): null;
		isset($jsonInfo->deletedWord) ? ($this->deletedWord = $jsonInfo->deletedWord) : null;
		isset($jsonInfo->suggest) ? ($this->suggest = $jsonInfo->suggest) : null;
		isset($jsonInfo->sidoCount) ? ($this->sidoCount = $jsonInfo->sidoCount) : null;
		isset($jsonInfo->numFound) ? ($this->numFound = $jsonInfo->numFound) : null;
		isset($jsonInfo->listSize) ? ($this->listSize = $jsonInfo->listSize) : null;
		isset($jsonInfo->totalPage) ? ($this->totalPage = $jsonInfo->totalPage) : null;
		isset($jsonInfo->page) ? ($this->page = $jsonInfo->page) : null;
		isset($jsonInfo->chargeYN) ? ($this->chargeYN = $jsonInfo->chargeYN) : false;

		if(isset($jsonInfo->juso)){
			$JusoList = array();
		
			for($i=0; $i < Count($jsonInfo->juso);$i++){
				$JusoListObj = new Juso();
				$JusoListObj->fromjsonInfo($jsonInfo->juso[$i]);
				$JusoList[$i] = $JusoListObj;
			}
			$this->juso = $JusoList;
		}
	}
}

class Juso{
	public $sectionNum;
	public $roadAddr1;
	public $roadAddr2;
	public $jibunAddr;
	public $detailBuildingName;
	public $zipcode;
	public $dongCode;
	public $streetCode;
	public $relatedJibun;

	function fromjsonInfo($jsonInfo){
		isset($jsonInfo->sectionNum) ? ($this->sectionNum = $jsonInfo->sectionNum) : null;
		isset($jsonInfo->roadAddr1) ? ($this->roadAddr1 = $jsonInfo->roadAddr1) : null;
		isset($jsonInfo->roadAddr2) ? ($this->roadAddr2 = $jsonInfo->roadAddr2) : null;
		isset($jsonInfo->jibunAddr) ? ($this->jibunAddr = $jsonInfo->jibunAddr) : null;
		isset($jsonInfo->detailBuildingName) ? $this->detailBuildingName = $jsonInfo->detailBuildingName : $this->detailBuildingName = array();
		isset($jsonInfo->zipcode) ? ($this->zipcode = $jsonInfo->zipcode) : null;
		isset($jsonInfo->dongCode) ? ($this->dongCode = $jsonInfo->dongCode) : null;
		isset($jsonInfo->streetCode) ? ($this->streetCode = $jsonInfo->streetCode) : null;
		isset($jsonInfo->relatedJibun) ? $this->relatedJibun = $jsonInfo->relatedJibun : $this->relatedJibun = array();
	}
}

class JusoLinkException extends Exception
{
	public function __construct($response,$code = -99999999, Exception $previous = null) {
       $Err = json_decode($response);
       if(is_null($Err)) {
       		parent::__construct($response, $code );
       }
       else {
       		parent::__construct($Err->message, $Err->code);
       }
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
?>