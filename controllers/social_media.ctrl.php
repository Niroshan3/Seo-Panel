<?php

/***************************************************************************
 *   Copyright (C) 2009-2011 by Geo Varghese(www.seopanel.in)  	           *
 *   sendtogeo@gmail.com   												   *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 *   This program is distributed in the hope that it will be useful,       *
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of        *
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         *
 *   GNU General Public License for more details.                          *
 *                                                                         *
 *   You should have received a copy of the GNU General Public License     *
 *   along with this program; if not, write to the                         *
 *   Free Software Foundation, Inc.,                                       *
 *   59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.             *
 ***************************************************************************/

# class defines all social media controller functions
class SocialMediaController extends Controller{
    
    var $linkTable = "social_media_links";
    var $linkReportTable = "social_media_link_results";
    var $layout = "ajax";
    var $pageScriptPath = 'social_media.php';
    var $serviceList;
    
    function __construct() {
    	 
    	$this->serviceList = [
    		"facebook" => [
    			"label" => "Facebook",
    			"regex" => [
    				"like" => '/id="PagesLikesCountDOMID.*?<span.*?>(.*?)<span/is',
    				"follower" => '/people like this.*?<div>(\d.*?)people follow this/is',
    			],
    			"url_part" => '?locale=en_US'
    		],
    		"twitter" => [
    			"label" => "Twitter",
    			"regex" => [
    				"follower" => '/\/followers".*?<div.*?>(.*?)<\/div>/is'
    			],
    		],
    		"instagram" => [
    			"label" => "Instagram",
    			"regex" => [
    				"follower" => '/edge_followed_by.*?"count":(.*?)\}/is'
    			],
    		],
    		/*"linkedin" => [
    			"label" => "LinkedIn",
    			"regex" => "",
    		],*/
    		"pinterest" => [
    			"label" => "Pinterest",
    			"regex" => [
    				"follower" => '/pinterestapp:followers.*?content="(.*?)"/is'
    			],
    		],
    		"youtube" => [
    			"label" => "Youtube",
    			"regex" => [
    				"follower" => '/aria-label=.*?subscribers.*?>(.*?)</is'
    			],
    		],
    	];
    
    	$this->set('pageScriptPath', $this->pageScriptPath);
    	$this->set( 'serviceList', $this->serviceList );
    	$this->set( 'pageNo', $_REQUEST['pageno']);
    	
    	parent::__construct();
    }
    
    function showSocialMediaLinks($searchInfo = '') {
    	$userId = isLoggedIn();
    	$this->set('searchInfo', $searchInfo);
    	$sql = "select l.*, w.name as website_name from $this->linkTable l, websites w where l.website_id=w.id";
    	
    	if (!isAdmin()) {
    	    $sql .= " and w.user_id=$userId";
    	}
    	
    	// search conditions
    	$sql .= !empty($searchInfo['name']) ? " and l.name like '%".addslashes($searchInfo['name'])."%'" : "";
    	$sql .= !empty($searchInfo['website_id']) ? " and l.website_id=".intval($searchInfo['website_id']) : "";
    	$sql .= !empty($searchInfo['type']) ? " and `type`='".addslashes($searchInfo['type'])."'" : "";
    	
    	if (!empty($searchInfo['status'])) {
    	    $sql .= ($searchInfo['status'] == 'active') ? " and l.status=1" : " and l.status=0"; 
    	}
    	
    	$webSiteCtrler = new WebsiteController();
    	$websiteList = $webSiteCtrler->__getAllWebsites($userId, true);
    	$this->set( 'websiteList', $websiteList );
    	 
    	// pagination setup
    	$this->db->query( $sql, true );
    	$this->paging->setDivClass( 'pagingdiv' );
    	$this->paging->loadPaging( $this->db->noRows, SP_PAGINGNO );
    	$pagingDiv = $this->paging->printPages( $this->pageScriptPath, 'searchForm', 'scriptDoLoadPost', 'content', '' );
    	$this->set( 'pagingDiv', $pagingDiv );
    	$sql .= " limit " . $this->paging->start . "," . $this->paging->per_page;
    	
    	$linkList = $this->db->select( $sql );
    	$this->set( 'list', $linkList );
    	$this->render( 'socialmedia/show_social_media_links');
    }
    
    function __checkName($name, $websiteId, $linkId = false){
        $whereCond = "name='".addslashes($name)."'";
        $whereCond .= " and website_id='".intval($websiteId)."'";
        $whereCond .= !empty($linkId) ? " and id!=".intval($linkId) : "";
        $listInfo = $this->dbHelper->getRow($this->linkTable, $whereCond);
        return empty($listInfo['id']) ? false :  $listInfo['id'];
    }
    
    function __checkUrl($url, $websiteId, $linkId = false){
        $whereCond = "url='".addslashes($url)."'";
        $whereCond .= " and website_id=".intval($websiteId);
        $whereCond .= !empty($linkId) ? " and id!=".intval($linkId) : "";
        $listInfo = $this->dbHelper->getRow($this->linkTable, $whereCond);
        return empty($listInfo['id']) ? false :  $listInfo['id'];
    }
    
    function validateSocialMediaLink($listInfo) {        
        $errMsg = [];
        $errMsg['name'] = formatErrorMsg($this->validate->checkBlank($listInfo['name']));
        $errMsg['url'] = formatErrorMsg($this->validate->checkBlank($listInfo['url']));
        $errMsg['website_id'] = formatErrorMsg($this->validate->checkBlank($listInfo['website_id']));
        $errMsg['type'] = formatErrorMsg($this->validate->checkBlank($listInfo['type']));
        
        if(!$this->validate->flagErr){            
            if ($this->__checkName($listInfo['name'], $listInfo['website_id'], $listInfo['id'])) {
                $errMsg['name'] = formatErrorMsg($_SESSION['text']['label']['already exist']);
                $this->validate->flagErr = true;
            }
        }
        
        if(!$this->validate->flagErr){
            if ($this->__checkUrl($listInfo['url'], $listInfo['website_id'], $listInfo['id'])) {
                $errMsg['url'] = formatErrorMsg($_SESSION['text']['label']['already exist']);
                $this->validate->flagErr = true;
            }
        }
        
        if(!$this->validate->flagErr){
            if (!stristr($listInfo['url'], $listInfo['type'])) {
                $errMsg['url'] = formatErrorMsg($_SESSION['text']['common']["Invalid value"]);
                $this->validate->flagErr = true;
            }
        }
        
        // Validate link count
        if(!$this->validate->flagErr){
            $websiteCtrl = new WebsiteController();
            $websiteInfo = $websiteCtrl->__getWebsiteInfo($listInfo['website_id']);
            $newCount = !empty($listInfo['id']) ? 0 : 1;
            if (! $this->validateSocialMediaLinkCount($websiteInfo['user_id'], $newCount)) {
                $this->set('validationMsg', $this->spTextSMC['Your social media link count already reached the limit']);
                $this->validate->flagErr = true;
            }
        }
        
        return $errMsg;        
    }
    
    // Function to check / validate the user type social media count
    function validateSocialMediaLinkCount($userId, $newCount = 1) {
        $userCtrler = new UserController();
        
        // if admin user id return true
        if ($userCtrler->isAdminUserId($userId)) {
            return true;
        }
        
        $userTypeCtrlr = new UserTypeController();
        $userTypeDetails = $userTypeCtrlr->getUserTypeSpecByUser($userId);
        
        $whereCond = "l.website_id=w.id and w.user_id=".intval($userId);
        $existingInfo = $this->dbHelper->getRow("$this->linkTable l, websites w", $whereCond, "count(*) count");
        $userSMLinkCount = $existingInfo['count'];
        $userSMLinkCount += $newCount;
        
        // if limit is set and not -1
        if (isset($userTypeDetails['social_media_link_count']) && $userTypeDetails['social_media_link_count'] >= 0) {
            
            // check whether count greater than limit
            if ($userSMLinkCount <= $userTypeDetails['social_media_link_count']) {
                return true;
            } else {
                return false;
            }
            
        } else {
            return true;
        }
        
    }
    
    function newSocialMediaLink($info = '') {
        $userId = isLoggedIn();
        $this->set('post', $info);
        $webSiteCtrler = new WebsiteController();
        $websiteList = $webSiteCtrler->__getAllWebsites($userId, true);
        $this->set( 'websiteList', $websiteList );                
        $this->set('editAction', 'createSocialMediaLink');
        $this->render( 'socialmedia/edit_social_media_link');   
    }
    
    function createSocialMediaLink($listInfo = '') {
        
        $errMsg = $this->validateSocialMediaLink($listInfo);
        
        // if no error occured
        if (!$this->validate->flagErr) {
            $dataList = [
                'name' => $listInfo['name'],
                'url' => addHttpToUrl($listInfo['url']),
                'type' => $listInfo['type'],
                'website_id|int' => $listInfo['website_id'],
            ];
            $this->dbHelper->insertRow($this->linkTable, $dataList);
            $this->showSocialMediaLinks(['name' => $listInfo['name']]);
            exit;
        }
        
        $this->set('errMsg', $errMsg);
        $this->newSocialMediaLink($listInfo);
        
    }
    
    function editSocialMediaLink($linkId, $listInfo = '') {
        
        if (!empty($linkId)) {
            $userId = isLoggedIn();
            $webSiteCtrler = new WebsiteController();
            $websiteList = $webSiteCtrler->__getAllWebsites($userId, true);
            $this->set( 'websiteList', $websiteList );
            
            if(empty($listInfo)){
                $listInfo = $this->__getSocialMediaLinkInfo($linkId);
            }
            
            $this->set('post', $listInfo);           
            $this->set('editAction', 'updateSocialMediaLink');
            $this->render( 'socialmedia/edit_social_media_link');
        }
        
    }
    
    function updateSocialMediaLink($listInfo) {
        $this->set('post', $listInfo);
        $errMsg = $this->validateSocialMediaLink($listInfo);
        
        if (!$this->validate->flagErr) {
            $dataList = [
                'name' => $listInfo['name'],
                'url' => addHttpToUrl($listInfo['url']),
                'type' => $listInfo['type'],
                'website_id|int' => $listInfo['website_id'],
            ];
            $this->dbHelper->updateRow($this->linkTable, $dataList, "id=".intval($listInfo['id']));
            $this->showSocialMediaLinks(['name' => $listInfo['name']]);
            exit;
        }
        
        $this->set('errMsg', $errMsg);
        $this->editSocialMediaLink($listInfo['id'], $listInfo);
    }
    
    function deleteSocialMediaLink($linkId) {
        $this->dbHelper->deleteRows($this->linkTable, "id=" . intval($linkId));
        $this->showSocialMediaLinks();
    }
    
    function __changeStatus($linkId, $status){
        $linkId = intval($linkId);
        $this->dbHelper->updateRow($this->linkTable, ['status|int' => $status], "id=$linkId");
    }
    
    function __getSocialMediaLinkInfo($linkId) {
        $whereCond = "id=".intval($linkId);
        $info = $this->dbHelper->getRow($this->linkTable, $whereCond);
        return $info;
    }
    
    function verifyActionAllowed($linkId) {
        $allowed = true;
        
        // if not admin, check the permissions
        if (!isAdmin()) {
            $userId = isLoggedIn();
            $linkInfo = $this->__getSocialMediaLinkInfo($linkId);
            $webSiteCtrler = new WebsiteController();
            $webSiteInfo = $webSiteCtrler->__getWebsiteInfo($linkInfo['website_id']);
            $allowed = ($userId == $webSiteInfo['user_id']) ? true : false;
        }
        
        if (!$allowed) {
            showErrorMsg($_SESSION['text']['label']['Access denied']); 
        }
                
    }

	function viewQuickChecker($info='') {	
		$userId = isLoggedIn();
		$this->render('socialmedia/quick_checker');
	}

	function doQuickChecker($listInfo = '') {
		
		if (!stristr($listInfo['url'], $listInfo['type'])) {
			$errorMsg = formatErrorMsg($_SESSION['text']['common']["Invalid value"]);
			$this->validate->flagErr = true;
		}
		
		// if no error occured find social media details
		if (!$this->validate->flagErr) {
			$smLink = addHttpToUrl($listInfo['url']);
			$result = $this->getSocialMediaDetails($listInfo['type'], $smLink);
			
			// if call is success
			if ($result['status']) {
				$this->set('smType', $listInfo['type']);
				$this->set('smLink', $smLink);
				$this->set('statInfo', $result);
				$this->render('socialmedia/quick_checker_results');
				exit;
			} else {
				$errorMsg = $result['msg'];
			}
			
		}
		
		$errorMsg = !empty($errorMsg) ? $errorMsg : $_SESSION['text']['common']['Internal error occured'];
		showErrorMsg($errorMsg);
		
	}	
	
	function getSocialMediaDetails($smType, $smLink) {
		$result = ['status' => 0, 'likes' => 0, 'followers' => 0, 'msg' => $_SESSION['text']['common']['Internal error occured']];
		$smInfo = $this->serviceList[$smType];
		
		if (!empty($smInfo) && !empty($smLink)) {
			
			// if params needs to be added with url
			if (!empty($smInfo['url_part'])) {
				$smLink .= stristr($smLink, '?') ? str_replace("?", "&", $smInfo['url_part']) : $smInfo['url_part'];
			}
			
			$smContentInfo = $this->spider->getContent($smLink);
			
			if (!empty($smContentInfo['page'])) {

				// find likes
				if (!empty($smInfo['regex']['like'])) {
					preg_match($smInfo['regex']['like'], $smContentInfo['page'], $matches);
					
					if (!empty($matches[1])) {
						$result['status'] = 1;
						$result['likes'] = formatNumber($matches[1]);
					}
					
				}
				
				// find followers
				if (!empty($smInfo['regex']['follower'])) {
					preg_match($smInfo['regex']['follower'], $smContentInfo['page'], $matches);
						
					if (!empty($matches[1])) {
						$result['status'] = 1;
						$result['followers'] = formatNumber($matches[1]);
					}
						
				}
				
			} else {
				$result['msg'] = $smContentInfo['errmsg'];
			}
			
		}
		
		return $result;
		
	}
	
	/*
	 * function to get all links with out reports for a day
	 */
	function getAllLinksWithOutReports($websiteId, $date) {
		$websiteId = intval($websiteId);
		$date = addslashes($date);
		$sql = "select link.*, lr.id result_id from social_media_links link left join 
			social_media_link_results lr on (link.id=lr.sm_link_id and lr.report_date='$date') 
			where link.status=1 and link.website_id=$websiteId and lr.id is NULL";
		
		$linkList = $this->db->select($sql);
		return $linkList;
		
	}
	
	function saveSocialMediaLinkResults($linkId, $linkInfo) {		
		$dataList = [
			'sm_link_id|int' => $linkId,
			'likes|int' => $linkInfo['likes'],
			'followers|int' => $linkInfo['followers'],
			'report_date' => date('Y-m-d'),
		];
		
		$this->dbHelper->insertRow($this->linkReportTable, $dataList);		
	}
	
	/*
	 * func to show report summary
	 */ 
	function viewReportSummary($searchInfo = '', $summaryPage = false, $cronUserId=false) {
	
		$userId = !empty($cronUserId) ? $cronUserId : isLoggedIn();
		$source = $this->sourceList[0];
		$this->set('summaryPage', $summaryPage);
		$this->set('searchInfo', $searchInfo);
		$this->set('cronUserId', $cronUserId);
	
		$exportVersion = false;
		switch($searchInfo['doc_type']){
	
			case "export":
				$exportVersion = true;
				$exportContent = "";
				break;
					
			case "pdf":
				$this->set('pdfVersion', true);
				break;
					
			case "print":
				$this->set('printVersion', true);
				break;
		}
	
		$fromTime = !empty($searchInfo['from_time']) ? addslashes($searchInfo['from_time']) : date('Y-m-d', strtotime('-4 days'));
		$toTime = !empty($searchInfo['to_time']) ? addslashes($searchInfo['to_time']) : date('Y-m-d', strtotime('-3 days'));
		$this->set('fromTime', $fromTime);
		$this->set('toTime', $toTime);
	
		$websiteController = New WebsiteController();
		$wList = $websiteController->__getAllWebsites($userId, true);
		$websiteList = array(0);
		foreach ($wList as $wInfo) $websiteList[$wInfo['id']] = $wInfo;
		$this->set('websiteList', $websiteList);
		$websiteId = intval($searchInfo['website_id']);
		$this->set('websiteId', $websiteId);
	
		// to find order col
		if (!empty($searchInfo['order_col'])) {
			$orderCol = $searchInfo['order_col'];
			$orderVal = getOrderByVal($searchInfo['order_val']);
		} else {
			$orderCol = "clicks";
			$orderVal = 'DESC';
		}
	
		$this->set('orderCol', $orderCol);
		$this->set('orderVal', $orderVal);
		$scriptName = $summaryPage ? "archive.php" : "webmaster-tools.php";
		$scriptPath = SP_WEBPATH . "/$scriptName?sec=viewKeywordReports&website_id=$websiteId";
		$scriptPath .= "&from_time=$fromTime&to_time=$toTime&search_name=" . $searchInfo['search_name'];
		$scriptPath .= "&order_col=$orderCol&order_val=$orderVal&report_type=keyword-search-reports";
	
		// set website id to get exact keywords of a user
		if (!empty($websiteId)) {
			$conditions = " and k.website_id=$websiteId";
		} else {
			$conditions = " and k.website_id in (".implode(',', array_keys($websiteList)).")";
		}
	
		$conditions .= !empty($searchInfo['search_name']) ? " and k.name like '%".addslashes($searchInfo['search_name'])."%'" : "";
	
		$subSql = "select [cols] from webmaster_keywords k, keyword_analytics r where k.id=r.keyword_id
		and k.status=1 $conditions and r.source='$source' and r.report_date='$toTime'";
	
		$sql = "
		(" . str_replace("[cols]", "k.id,k.name,k.website_id,r.clicks,r.impressions,r.ctr,r.average_position", $subSql) . ")
			UNION
			(select k.id,k.name,k.website_id,0,0,0,0 from webmaster_keywords k where k.status=1 $conditions
			and k.id not in (". str_replace("[cols]", "distinct(k.id)", $subSql) ."))
		order by " . addslashes($orderCol) . " " . addslashes($orderVal);
	
		if ($orderCol != 'name') $sql .= ", name";
	
		// pagination setup, if not from cron job email send function, pdf and export action
		if (!in_array($searchInfo['doc_type'], array("pdf", "export"))) {
			$this->db->query($sql, true);
			$this->paging->setDivClass('pagingdiv');
			$this->paging->loadPaging($this->db->noRows, SP_PAGINGNO);
			$pagingDiv = $this->paging->printPages($scriptPath, '', 'scriptDoLoad', 'content', "");
			$this->set('pagingDiv', $pagingDiv);
			$this->set('pageNo', $searchInfo['pageno']);
			$sql .= " limit ".$this->paging->start .",". $this->paging->per_page;
		}
	
		# set report list
		$baseReportList = $this->db->select($sql);
		$this->set('baseReportList', $baseReportList);
		$this->set('colList', $this->colList);
	
		// if keywords existing
		if (!empty($baseReportList)) {
				
			$keywordIdList = array();
			foreach ($baseReportList as $info) {
				$keywordIdList[] = $info['id'];
			}
	
			$sql = "select k.id,k.name,k.website_id,r.clicks,r.impressions,r.ctr,r.average_position
			from webmaster_keywords k, keyword_analytics r where k.id=r.keyword_id
			and k.status=1 $conditions and r.source='$source' and r.report_date='$fromTime'";
			$sql .= " and k.id in(" . implode(",", $keywordIdList) . ")";
			$reportList = $this->db->select($sql);
			$compareReportList = array();
				
			foreach ($reportList as $info) {
				$compareReportList[$info['id']] = $info;
			}
				
			$this->set('compareReportList', $compareReportList);
				
		}
	
		if ($exportVersion) {
			$spText = $_SESSION['text'];
			$reportHeading =  $this->spTextTools['Keyword Search Summary']."($fromTime - $toTime)";
			$exportContent .= createExportContent( array('', $reportHeading, ''));
			$exportContent .= createExportContent( array());
			$headList = array($spText['common']['Website'], $spText['common']['Keyword']);
	
			$pTxt = str_replace("-", "/", substr($fromTime, -5));
			$cTxt = str_replace("-", "/", substr($toTime, -5));
			foreach ($this->colList as $colKey => $colLabel) {
				if ($colKey == 'name') continue;
				$headList[] = $colLabel . "($pTxt)";
				$headList[] = $colLabel . "($cTxt)";
				$headList[] = $colLabel . "(+/-)";
			}
	
			$exportContent .= createExportContent($headList);
			foreach($baseReportList as $listInfo){
	
				$valueList = array($websiteList[$listInfo['website_id']]['url'], $listInfo['name']);
				foreach ($this->colList as $colName => $colVal) {
					if ($colName == 'name') continue;
						
					$currRank = isset($listInfo[$colName]) ? $listInfo[$colName] : 0;
					$prevRank = isset($compareReportList[$listInfo['id']][$colName]) ? $compareReportList[$listInfo['id']][$colName] : 0;
					$rankDiff = "";
	
					// if both ranks are existing
					if ($prevRank != '' && $currRank != '') {
						$rankDiff = $currRank - $prevRank;
						if ($colName == 'average_position') $rankDiff = $rankDiff * -1;
					}
	
					$valueList[] = $prevRank;
					$valueList[] = $currRank;
					$valueList[] = $rankDiff;
				}
	
				$exportContent .= createExportContent( $valueList);
			}
				
			if ($summaryPage) {
				return $exportContent;
			} else {
				exportToCsv('keyword_search_summary', $exportContent);
			}
				
		} else {
				
			// if pdf export
			if ($summaryPage) {
				return $this->getViewContent('webmaster/keyword_search_analytics_summary');
			} else {
				// if pdf export
				if ($searchInfo['doc_type'] == "pdf") {
					exportToPdf($this->getViewContent('webmaster/keyword_search_analytics_summary'), "keyword_search_summary_$fromTime-$toTime.pdf");
				} else {
					$this->set('searchInfo', $searchInfo);
					$this->render('webmaster/keyword_search_analytics_summary');
				}
			}
				
		}
	}
	
}
?>