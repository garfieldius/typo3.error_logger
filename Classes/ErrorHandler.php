<?php
/*                                                                     *
 * This file is brought to you by Georg Großberger                     *
 * (c) 2013 by Georg Großberger <contact@grossberger-ge.org>           *
 *                                                                     *
 * It is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License, either version 3       *
 * of the License, or (at your option) any later version.              *
 *                                                                     */

namespace GeorgGrossberger\ErrorLogger;

use TYPO3\CMS\Core\Error\ErrorHandlerInterface;
use TYPO3\CMS\Core\Error\Exception;

/**
 * An error handler that logs EVERYTHING
 *
 * @package GeorgGrossberger.ErrorLogger
 * @author Georg Großberger <contact@grossberger-ge.org>
 * @copyright 2013 by Georg Großberger
 * @license GPL v3 http://www.gnu.org/licenses/gpl-3.0.txt
 */
class ErrorHandler implements ErrorHandlerInterface {

	/**
	 * @var integer
	 */
	protected $exceptionalErrors = 0;

	/**
	 * @var integer
	 */
	protected $errorsToHandle = 0;

	/**
	 * @var resource
	 */
	protected $errorLog;

	/**
	 * @var integer
	 */
	protected $timeSpent = 0;

	/**
	 * Registers this class as default error handler
	 * @param integer $errorHandlerErrors The integer representing the E_* error level which should be
	 * @return \GeorgGrossberger\ErrorLogger\ErrorHandler
	 */
	public function __construct($errorHandlerErrors) {

		$this->errorsToHandle = $errorHandlerErrors & ~(E_COMPILE_WARNING | E_COMPILE_ERROR | E_CORE_WARNING | E_CORE_ERROR | E_PARSE | E_ERROR);
		set_error_handler(array($this, 'handleError'));

		$errorLog = PATH_site . 'typo3temp/logs/tx_errorlogger/error_' . date('Y-m-d') . '_';
		$i = '000001';
		while (is_file($errorLog . $i . '.log')) {
			$i = str_pad((int) $i + 1, 6, '0', STR_PAD_LEFT);
		}

		$errorLog = $errorLog . $i . '.log';
		$dir = dirname($errorLog);

		if (!is_dir($dir)) {
			mkdir($dir, 0775, TRUE);
		}

		touch($errorLog);
		chmod($errorLog, 0664);

		$this->errorLog = fopen($errorLog, 'a');
	}

	public function __destruct() {
		fclose($this->errorLog);
	}

	/**
	 * Defines which error levels should result in an exception thrown.
	 * @param integer $exceptionalErrors The integer representing the E_* error level to handle as exceptions
	 * @return void
	 */
	public function setExceptionalErrors($exceptionalErrors) {
		$this->exceptionalErrors = $exceptionalErrors;
	}

	/**
	 * Handles an error.
	 * If the error is registered as exceptionalError it will by converted into an exception, to be handled
	 * by the configured exceptionhandler. Additionall the error message is written to the configured logs.
	 * If TYPO3_MODE is 'BE' the error message is also added to the flashMessageQueue, in FE the error message
	 * is displayed in the admin panel (as TsLog message)
	 * @param integer $errorLevel The error level - one of the E_* constants
	 * @param string $errorMessage The error message
	 * @param string $errorFile Name of the file the error occurred in
	 * @param integer $errorLine Line number where the error occurred
	 * @return void
	 * @throws Exception with the data passed to this method if the error is registered as exceptionalError
	 */
	public function handleError($errorLevel, $errorMessage, $errorFile, $errorLine) {
		$tags        = array();
		$exceptional = FALSE;
		$request     = array();
		$ignore      = FALSE;

		if (error_reporting() == 0) {
			$ignore = TRUE;
			$tags[] = 'ignored';
		}

		if ($this->errorsToHandle & $errorLevel < 0) {
			$tags[] = 'no-handle';
		}

		if ($this->exceptionalErrors & $errorLevel > 0) {
			$tags[] = 'exceptional';
			$exceptional = TRUE;
		}

		if (TYPO3_MODE == 'FE') {
			$request[] = 'frontend';
		}

		if (TYPO3_MODE == 'BE') {
			$request[] = 'backend';
		}

		if (defined('TYPO3_cliMode') && TYPO3_cliMode) {
			$request[] = 'cli';
		}

		if ($GLOBALS['TYPO3_AJAX'] || !empty($_GET['eID'])) {
			$request[] = 'ajax';
		}

		if (defined('TYPO3_enterInstallScript') && TYPO3_enterInstallScript) {
			$request[] = 'install';
		}

		$errorLevels = array(
			E_COMPILE_ERROR => 'Compile Error',
			E_COMPILE_WARNING => 'Compile Warning',
			E_CORE_ERROR => 'Core Error',
			E_CORE_WARNING => 'Core Warning',
			E_PARSE => 'Parse Error',
			E_ERROR => 'Error',
			E_WARNING => 'Warning',
			E_NOTICE => 'Notice',
			E_USER_ERROR => 'User Error',
			E_USER_WARNING => 'User Warning',
			E_USER_NOTICE => 'User Notice',
			E_STRICT => 'Runtime Notice',
			E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
			E_DEPRECATED => 'Runtime Deprecation Notice'
		);

		$msg =
			date('Y.m.d H:i:s') .
			'; ' .$errorLevels[$errorLevel] . (count($tags) > 0 ? ' (' . implode(',', $tags) . ')' : '') .
			'; Request Type: ' .implode(',', $request) .
			"; $errorMessage; $errorFile; $errorLine\n";

		fwrite($this->errorLog, $msg);

		if ($exceptional && !$ignore) {
			throw new Exception($msg, $errorLevel);
		}
	}

}
