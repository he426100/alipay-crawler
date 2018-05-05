<?php

namespace Lib\Utils;

final class Session
{

	/**
	 * Get one session var
	 *
	 * @param $key
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get($key, $default = null)
	{
		if (array_key_exists($key, $_SESSION)) {
			return $_SESSION[$key];
		}
		return $default;
	}

	/**
	 * Set one session var
	 *
	 * @param mixed $key
	 * @param mixed $value
	 * @return void
	 */
	public static function set($key, $value)
	{
		$_SESSION[$key] = $value;
	}

	/**
	 * Delete one session var by key
	 *
	 * @param $key
	 * @return void
	 */
	public static function delete($key)
	{
		if (array_key_exists($key, $_SESSION)) {
			unset($_SESSION[$key]);
		}
	}

	/**
	 * Clear all session vars
	 *
	 * @return void
	 */
	public static function clearAll()
	{
		$_SESSION = [];
	}

	/**
	 * Regenerate current session id
	 *
	 * @return void
	 */
	public static function regenerate()
	{
		if (session_status() == PHP_SESSION_ACTIVE) {
			session_regenerate_id(true);
		}
	}

	/**
	 * Destroy current session and delete session cookie
	 *
	 * @return void
	 */
	public static function destroy()
	{
		$_SESSION = [];
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params["path"],
				$params["domain"],
				$params["secure"],
				$params["httponly"]
			);
		}
		if (session_status() == PHP_SESSION_ACTIVE) {
			session_destroy();
		}
	}

}