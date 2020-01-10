<?php
namespace In2code\Powermail\Finisher;

use In2code\Powermail\Domain\Model\Mail;
use In2code\Powermail\Finisher\AbstractFinisher;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class addEventIntoDbFinisher
 *
 * @package In2code\Powermail\Finisher
 */
class addEventIntoDbFinisher extends AbstractFinisher
{

	/**
	 * @var Mail
	 */
	protected $mail;

	/**
	 * @var array
	 */
	protected $configuration;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * Will be called always at first
	 *
	 * @return void
	 */
	public function initializeFinisher()
	{
	}

	/**
	 * Will be called before addEventIntoDbFinisher()
	 *
	 * @return void
	 */
	public function initializeAddEventIntoDbFinisher()
	{
	}

	/**
	 * Gets a time span - if possible - from 2 times
	 *
	 * @return int
	 */
	public function getTimeSpan($timeStartStr,$timeEndStr)
	{
		$timeStartPartArr = explode(":",$timeStartStr);
		$timeEndPartArr = explode(":",$timeEndStr);
		if(isset($timeStartPartArr[1]) && isset($timeEndPartArr[1])) {
			return abs(intval($timeStartPartArr[0]) - intval($timeEndPartArr[0]));
		} else {
			return false;
		}
	}

	/**
	 * Gets a valid date string in db format - if possible - from date
	 *
	 * @return date
	 */
	public function getSqlDateFromDateStr($dateStr)
	{
		$defaultDate = "2020-01-01";

		$datePartArr = explode(".",$dateStr);

		if(isset($datePartArr[2])) {
			if(checkdate((int)$datePartArr[1],(int)$datePartArr[0],(int)$datePartArr[2])) {
				return date("Y-m-d", strtotime((int)$datePartArr[2]."-".(int)$datePartArr[1]."-".(int)$datePartArr[0]." 00:00:00"));
			} else {
				return $defaultDate;
			}
		} else {
			return $defaultDate;
		}
	}

	/**
	 * Gets a valid time string in db format - if possible - from time
	 *
	 * @return time
	 */
	public function getSqlTimeFromTimeStr($timeStr)
	{
		$defaultTime = "00:00:00";

		$timePartArr = explode(":",$timeStr);

		if(isset($timePartArr[1])) {
			$hh = (int)$timePartArr[0];
			$mm = (int)$timePartArr[1];
			if($hh>=0 && $hh<=23 && $mm>=0 && $mm<=59) {
				return str_pad($hh, 2, "00", STR_PAD_LEFT).":".str_pad($mm, 2, "00", STR_PAD_LEFT).":00";
			} else {
				return $defaultTime;
			}
		} else {
			return $defaultTime;
		}
	}

	/**
	 * Gets a valid date time string in db format - if possible - from date and time
	 *
	 * @return datetime
	 */
	public function getSqlDateTimeFromDateAndTimeStr($dateStr,$timeStr)
	{
		$defaultDateTime = "2020-01-01 00:00:00";

		$datePartArr = explode(".",$dateStr);
		$timePartArr = explode(":",$timeStr);

		if(isset($datePartArr[2]) && isset($timePartArr[1])) {
			if(checkdate((int)$datePartArr[1],(int)$datePartArr[0],(int)$datePartArr[2])) {
				return date("Y-m-d H:i:s", strtotime((int)$datePartArr[2]."-".(int)$datePartArr[1]."-".(int)$datePartArr[0]." ".(int)$timePartArr[0].":".(int)$timePartArr[1].":00"));
			} else {
				return $defaultDateTime;
			}
		} else {
			return $defaultDateTime;
		}
	}

	/**
	 * addEventIntoDbFinisher
	 *
	 * @return void
	 */
	public function addEventIntoDbFinisher()
	{
/*
	//	get value from configuration
		$foo = $this->configuration["foo"];

	//	get subject from mail
		$subject = $this->getMail()->getSubject();

	//	get a value by markername
		$value = $this->getMail()->getAnswersByFieldMarker()["markerName"]->getValue();

	//	get a value by field uid
		$value = $this->getMail()->getAnswersByFieldUid()[123]->getValue();
*/
	//	Build a connection to the eventmanager database
	//	-----------------------------------------------
	//	Simplify post array
		$eventArr = $_POST["tx_powermail_pi1"]["field"];
	
	//	Build hash, maybe we need it for some other tables later
		$hashStr = md5(time());

	//	Build key/value pairs for insert statement
		$newRecordEventsArr = [
			"event_dauer"		=> intval($this->getTimeSpan($eventArr["beginnhhmm"],$eventArr["endehhmm"])),
			"event_name"		=> $eventArr["veranstaltungsname"],
			"event_ktext"		=> $eventArr["kurzertextfuerterminlistenansicht"],
			"event_text"		=> $eventArr["langertextfuertermindetailsansicht"],
			"category_id"		=> intval($eventArr["veranstaltungsart"]),
			"event_url"			=> $eventArr["webseite"],
			"event_start"		=> $this->getSqlDateTimeFromDateAndTimeStr($eventArr["datumttmmjjjj"],$eventArr["beginnhhmm"]),	// datetime (YYYY-mm-dd HH:MM:SS)
			"event_end"			=> $this->getSqlDateTimeFromDateAndTimeStr($eventArr["datumttmmjjjj"],$eventArr["endehhmm"]),	// datetime (YYYY-mm-dd HH:MM:SS)
			"event_datum1"		=> $this->getSqlDateFromDateStr($eventArr["datumttmmjjjj"]),		// date (YYYY-mm-dd)
			"event_datum2"		=> $this->getSqlDateFromDateStr($eventArr["datumttmmjjjj"]),		// date (YYYY-mm-dd)
			"event_zeiten1_name"	=> $this->getSqlDateFromDateStr($eventArr["datumttmmjjjj"]),	// varchar(255)
			"event_zeiten1_beginn"	=> $this->getSqlTimeFromTimeStr($eventArr["beginnhhmm"]),		// time (HH:MM:SS)
			"event_zeiten1_ende"	=> $this->getSqlTimeFromTimeStr($eventArr["endehhmm"]),			// time (HH:MM:SS)
			"event_location_text"	=> $eventArr["veranstaltungsort"].": ".$eventArr["veranstaltungsortfreitextergaenzung"],
			"event_preis"		=> $eventArr["preis"],
			"event_created"		=> date("Y-m-d H:i:s"),
			"event_hash"		=> $hashStr,
			"announcer_name"	=> $eventArr["name"],
			"announcer_fone"	=> $eventArr["telefonnummer"],
			"announcer_email"	=> $eventArr["e_mail_adresse"],
			"announcer_ip"		=> $_SERVER["REMOTE_ADDR"],
			"event_export_mdv"	=> "1"
		];
	//	Table names of cross databases (others than default) must be mapped to its database first in ["BE"]["TableMapping"] in LocalConfiguration.php
		$tableNameEvents = "eventM_events";

	//	Make a query builder object and do INSERT
		$queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableNameEvents);
		$queryBuilder->insert($tableNameEvents)->values($newRecordEventsArr)->execute();

	}
}