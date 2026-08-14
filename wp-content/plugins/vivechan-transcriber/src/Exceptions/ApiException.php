<?php

namespace Vivechan\Exceptions;

defined('ABSPATH') || exit;

/**
 * Exception thrown for a non-transient provider/network error.
 * The message is already user friendly.
 */
final class ApiException extends \RuntimeException {

	/**
	 * Seconds to wait before retrying when this is a rate-limit error.
	 *
	 * @var int|null
	 */
	public $retry_after = null;

	/**
	 * @var bool
	 */
	public $is_retryable = false;

	public function __construct( $message, $is_retryable = false, $retry_after = null ) {
		$this->is_retryable = (bool) $is_retryable;
		$this->retry_after  = null !== $retry_after ? max( 1, (int) $retry_after ) : null;
		parent::__construct( $message );
	}
}
