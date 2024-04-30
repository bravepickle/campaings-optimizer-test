<?php
/*

The task:
 Our advertising platform promotes mobile applications , it contains a campaign for each such application
 our publishers bring users that install and then use these applications
 the platform is reported about the install event and other application usage events of these users
 for example "app_open", "registration" and "purchase" events
 this stream of events is saved in a database

 To achieve quality goals we optimize campaigns by blacklisting publishers who do not qualify  the campaign's expections

 For example, a campaign may expect the number of "purchase" events a publisher brings to be equal or
 greater than 10% of the number of installs that publishers brought,
 or else the publisher should be blacklisted on that campaign

 To maintain these publisher blacklists we have a job process (OptimizationJob) runs every hour

 Campaign objects contain an optimizationProps object that includes the following properties:
 * sourceEvent and measuredEvent - in the above example sourceEvent would be "install" and measuredEvent
   would be "purchase"
 * threshold - the minimum of occurrences of sourceEvent, if a publisher has less sourceEvents that the threshold ,
   then she should not be blacklisted
 * ratioThreshold - the minimum ratio of sourceEvent occurrences to measuredEvent occurrences

 Event objects contain their type, the campaignId and publisherId

 Below is the begining of the implementation of the OptimizationJob class,
 A. complete the implementation maintaining campaigns' publishers blacklists
    Keep in mind that blacklisted publishers can only be removed from the blacklist if they cross the ratio

 B. make sure publishers are notified with an email whenever they are added or removed from a campaign's blacklist
    Please do not implement the email mechanism - we assume you know how to send an email

 */


class OptimizationJob {

	public function run() {
		$campaignDS = new CampaignDataSource();

		// array of Campagin objects
		$campaigns = $campaignDS->getCampaigns();


		$eventsDS = new EventsDataSource();
		/** @var Event $event */
		foreach($eventsDS->getEventsSince("2 weeks ago") as $event) {
			// START HERE
		}

	}
}


class Campaign {
	/** @var  OptimizationProps $optProps */
	private $optProps;

	/** @var  int */
	private $id;

	/** @var  array */
	private $publisherBlacklist;

	public function getOptimizationProps() {
		return $this->optProps;
	}
	public function getBlackList() {
		return $this->publisherBlacklist;
	}
	public function saveBlacklist($blacklist) {
		// dont implement
	}
}

class OptimizationProps {
	public $threshold, $sourceEvent, $measuredEvent, $ratioThreshold;
}

class Event {
	private $type;
	private $campaignId;
	private $publisherId;

	public function getType() {
		// for example "install"
		return $this->type;
	}
	public function getTs() {
		return $this->ts;
	}
	public function getCampaignId() {
		return $this->campaignId;
	}
	public function getPublisherId() {
		return $this->publisherId;
	}
}
