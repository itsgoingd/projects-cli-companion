<?php namespace ProjectsCliCompanion\Web;

use Goutte\Client;

class Web
{
	protected $baseUrl;
	protected $username;
	protected $password;

	protected $client;

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
		$crawler = $this->request('post', 'login', [
			'login'    => $username,
			'password' => $password
		]);

		$alerts = $crawler->filter('.alert');

		if (count($alerts) && strpos($alerts->first()->text(), 'Invalid user name or password, please try again.') !== false) {
			return false;
		}
	}

	public function postTicketComment($projectSlug, $ticketId, $title, $message, $status = null)
	{
		$this->login($this->username, $this->password);

		$ticketTypeStatuses = [
			'bug'    => [ 'progress' => 9,  'done' => 11, 'doneResolution' => 6 ],
			'change' => [ 'progress' => 15, 'done' => 17, 'doneResolution' => 14 ],
			'task'   => [ 'progress' => 3,  'done' => 5,  'doneResolution' => 1 ]
		];

		$statusId = $timesheetEntryId = $resolutionId = null;

		if ($status && $type = $this->getTicketType($projectSlug, $ticketId)) {
			$statusId = $ticketTypeStatuses[$type][$status];

			if ($status == 'done') {
				$timesheetEntryId = $this->getLastTimesheetEntryId($projectSlug, $ticketId);
				$resolutionId = $ticketTypeStatuses[$type]['doneResolution'];
			}
		}

		$response = $this->request('post', "tickets/{$projectSlug}/{$ticketId}/posted-from-cli", [
			'title'                          => $title,
			'new_status_id'                  => $status,
			'resolved_in_timesheet_entry_id' => $timesheetEntryId,
			'resolution_id'                  => $resolutionId,
			'comment'                        => $message,
			'comment_type'                   => 'markdown'
		]);
	}

	public function getTicketType($projectSlug, $ticketId)
	{
		$response = $this->request('get', "tickets/{$projectSlug}/{$ticketId}/request-from-cli");

		$typesMap = [
			'Bug'            => 'bug',
			'Change request' => 'change',
			'Task'           => 'task'
		];

		$webType = $response->filter('table')->eq(0)->filter('tr')->eq(5)->filter('td')->eq(1)->text();

		return isset($typesMap[$webType]) ? $typesMap[$webType] : null;
	}

	public function getLastTimesheetEntryId($projectSlug, $ticketId)
	{
		$response = $this->request('get', "tickets/{$projectSlug}/{$ticketId}/request-from-cli");

		return $response->filter('#resolved_in_timesheet_entry_id')->attr('value');
	}
}
