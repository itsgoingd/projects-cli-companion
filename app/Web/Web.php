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

		if ($status == 'done') {
			$status = 5;
		}

		$this->request('post', "tickets/{$projectSlug}/{$ticketId}/posted-from-cli", [
			'title'         => $title,
			'new_status_id' => $status,
			'comment'       => $message,
			'comment_type'  => 'markdown'
		]);

		echo((string) $this->response());
	}
}