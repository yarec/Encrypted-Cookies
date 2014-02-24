<?php
namespace Rosio\EncryptedCookie;

use Rosio\EncryptedCookie\CryptoSystem\iCryptoSystem;
use Rosio\EncryptedCookie\Exception\InputTooLargeException;
use Rosio\EncryptedCookie\Exception\InputTamperedException;
use Rosio\EncryptedCookie\Exception\InputExpiredException;
use Rosio\EncryptedCookie\Exception\TIDMismatchException;

class EncryptedCookie
{
	protected $name;

	protected $data;
	protected $domain;
	protected $path;
	protected $expiration;
	protected $isSecure;
	protected $isHttpOnly;

	/**
	 * The system which encryptes and decryptes cookies.
	 */
	protected $cryptoSystem;

	/**
	 * The system which stores and retrieves cookies.
	 */
	protected $cookieStorage;

	function __construct ($name)
	{
		$this->name = $name;

		$this->domain = '';
		$this->path = '/';
		$this->expiration = 0;
		$this->isSecure = false;
		$this->isHttpOnly = true;

		// If the cookie exists, get it's information
		$this->load();
	}

	/**
	 * Load a cookie's data if available.
	 *
	 * @throws InputTamperedException If The returned cookie was modified locally.
	 */
	function load ()
	{
		if (!$this->cookieStorage->has($this->name))
			return false;

		$data = $this->cookieStorage->get($this->name);

		try {
			$data = $this->cryptoSystem->decrypt($data);
		}
		catch (Exception $e) {
			return false;
		}

		$this->data = $data;

		return $this;
	}

	/**
	 * Save a cookie's data.
	 *
	 * @throws InputTooLargeException If the encrypted cookie is larger than 4kb (the max size a cookie can be).
	 */
	function save ()
	{
		$this->cookieStorage->set($this);
		
		return $this;
	}

	/* =============================================================================
	   Setters
	   ========================================================================== */
	
	/**
	 * Set the cryptographic system used to encrypt/decrypt the cookie.
	 * @param iCryptoSystem $cryptoSystem
	 */
	function setCryptoSystem (iCryptoSystem $cryptoSystem)
	{
		$this->cryptoSystem = $cryptoSystem;

		return $this;
	}

	/**
	 * Set the system used to store and retrieve cookies.
	 * This has been abstracted mainly for testing purposes.
	 * @param CookieStorage $cookieStorage
	 */
	function setCookieStorage (CookieStorage $cookieStorage)
	{
		$this->cookieStorage = $cookieStorage;

		return $this;
	}

	/**
	 * Set the data to be stored in the cookie.
	 * Max is about 2.8kb (encrypting adds size).
	 * @param mixed $data Data to be stored in the cookie.  If it isn't a string it will be serialized.
	 */
	function setData ($data)
	{
		$this->data = serialize($data);

		return $this;
	}

	/**
	 * Set the expiration of the cookie.  This also corresponds to the date the cookie's data is considered invalid.
	 * @param int $expiration A Unix timestamp.
	 *
	 * @throws InvalidArgumentException If the given expiration isn't a valid unix timestamp.
	 */
	function setExpiration ($expiration)
	{
		if (!is_numeric($expiration) || $expiration < 0)
			throw new \InvalidArgumentException('Expiration must be a unix timestamp.');

		$this->expiration = $expiration;

		return $this;
	}

	/**
	 * The domain the cookie is valid for.
	 * @param string $domain
	 */
	function setDomain ($domain)
	{
		$this->domain = $domain;

		return $this;
	}

	/**
	 * The path the cookie is valid for.
	 * @param string $path
	 */
	function setPath ($path)
	{
		$this->path = $path;

		return $this;
	}

	/**
	 * Should this cookie only be sent to the server if there is a secure connection?
	 * @param boolean $isSecure
	 */
	function setSecure ($isSecure)
	{
		$this->isSecure = $isSecure;

		return $this;
	}

	/**
	 * Should this cookie only be available to the server (and not client-side scripts)?
	 * @param boolean $isHttpOnly
	 */
	function setHttpOnly ($isHttpOnly)
	{
		$this->isHttpOnly = $isHttpOnly;

		return $this;
	}

	/* =============================================================================
	   Getters
	   ========================================================================== */
	function getData ()
	{
		return unserialize($this->data);
	}

	function getEncryptedData ()
	{
		$edata = $this->cryptoSystem->encrypt($this->data, $this->expiration);

		if (strlen($edata) >= 4096)
			throw new InputTooLargeException('Total encrypted data must be less than 4kb, or it will be truncated on the client.');

		return $edata;
	}

	function getExpiration ()
	{
		return $this->expiration;
	}

	function getPath ()
	{
		return $this->path;
	}

	function getDomain ()
	{
		return $this->domain;
	}

	function isSecure ()
	{
		return $this->isSecure;
	}

	function isHttpOnly ()
	{
		return $this->isHttpOnly;
	}
}