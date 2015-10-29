<?php namespace ProjectsCliCompanion\Web;

use Goutte\Client;

class Web
{
	protected $baseUrl;
	protected $username;
	protected $password;

	protected $client;
	protected $loggedIn = false;

	public function __construct($serverName, $username = null, $password = null)
	{
		$this->baseUrl = "https://{$serverName}/";
		$this->username = $username;
		$this->password = $password;

		$this->client = new Client();
	}

	protected function request($method, $url, $parameters = [])
	{
		$url = $this->baseUrl . $url;

		return $this->client->request($method, $url, $parameters);
	}

	protected function response()
	{
		return $this->client->getResponse();
	}

	public function login($username, $password)
	{
		if ($this->loggedIn) {
			return true;
		}

		$crawler = $this->request('post', 'login', [
			'login'    => $username,
			'password' => $password
		]);

		$alerts = $crawler->filter('.alert');

		if (count($alerts) && strpos($alerts->first()->text(), 'Invalid user name or password, please try again.') !== false) {
			return false;
		}

		$this->loggedIn = true;
	}

	public function postTicketComment($projectSlug, $ticketId, $title, $message, $status = null)
	{
		$this->login($this->username, $this->password);

		$ticketMetadata = $this->getTicketMetadata($projectSlug, $ticketId);

		$ticketTypeStatuses = [
			'bug'    => [ 'progress' => 9,  'done' => 11, 'doneResolution' => 6 ],
			'change' => [ 'progress' => 15, 'done' => 17, 'doneResolution' => 14 ],
			'task'   => [ 'progress' => 3,  'done' => 5,  'doneResolution' => 1 ]
		];

		$statusId = $timesheetEntryId = $resolutionId = null;

		if ($status) {
			$type = $ticketMetadata['type'];
			$statusId = $ticketTypeStatuses[$type][$status];

			if ($status == 'done') {
				$timesheetEntryId = $ticketMetadata['lastTimesheetEntryId'];
				$resolutionId = $ticketTypeStatuses[$type]['doneResolution'];
			}
		}

		$request = [
			'title'                          => $title,
			'new_status_id'                  => $statusId,
			'resolved_in_timesheet_entry_id' => $timesheetEntryId,
			'resolution_id'                  => $resolutionId,
			'comment'                        => $message,
			'comment_type'                   => 'markdown'
		];

		if ($ticketMetadata['returnToListing']) {
			$request['return_to_listing'] = 1;
		}

		$response = $this->request('post', "tickets/{$projectSlug}/{$ticketId}/posted-from-cli", $request);
	}

	public function getTicketMetadata($projectSlug, $ticketId)
	{
		$response = $this->request('get', "tickets/{$projectSlug}/{$ticketId}/request-from-cli");

		return [
			'type'                 => $this->getTicketType($response),
			'lastTimesheetEntryId' => $response->filter('#resolved_in_timesheet_entry_id')->attr('value'),
			'returnToListing'      => $response->filter('#return_to_listing')->attr('checked')
		];
	}

	protected function getTicketType($response)
	{
		$typesMap = [
			'Bug'            => 'bug',
			'Change request' => 'change',
			'Task'           => 'task'
		];

		$webType = $response->filter('table')->eq(0)->filter('tr')->eq(5)->filter('td')->eq(1)->text();

		return isset($typesMap[$webType]) ? $typesMap[$webType] : null;
	}
}
