<?php

namespace App\Http\Site;
use \App\Http\Controller;

class Welcome extends Controller
{

	public function index()
	{
		// log some message
		$this->logger->info("log a message");

		// sending a response
		return $this->view->render('site::test/welcome');
	}

}