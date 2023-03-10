<?php
/**
 * Copyright © 2001-2003 Jeff Dairiki
 * Copyright © 2001-2002 Carsten Klapp
 * Copyright © 2002,2004-2008 Reini Urban
 *
 * This file is part of PhpWiki.
 *
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with PhpWiki; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 */

if (isset($GLOBALS['ErrorManager'])) return;

define ('EM_FATAL_ERRORS', E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | ~2048 & (~E_DEPRECATED));
define ('EM_WARNING_ERRORS', E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED);
define ('EM_NOTICE_ERRORS', E_NOTICE | E_USER_NOTICE);

/* It is recommended to leave assertions on.
   You can simply comment the two lines below to leave them on.
   Only where absolute speed is necessary you might want to turn
   them off.
*/
if (defined('DEBUG') and DEBUG)
    assert_options(ASSERT_ACTIVE, 1);
else
    assert_options(ASSERT_ACTIVE, 0);
assert_options(ASSERT_CALLBACK, 'wiki_assert_handler');

function wiki_assert_handler($file, $line, $code)
{
    ErrorManager_errorHandler($code, sprintf("<br />%s:%s: %s: Assertion failed <br />", $file, $line, $code), $file, $line);
}

/**
 * A class which allows custom handling of PHP errors.
 *
 * This is a singleton class. There should only be one instance
 * of it --- you can access the one instance via $GLOBALS['ErrorManager'].
 *
 * FIXME: more docs.
 */
class ErrorManager
{
    /**
     * As this is a singleton class, you should never call this.
     */
    function __construct()
    {
        $this->_handlers = array();
        $this->_fatal_handler = false;
        $this->_postpone_mask = 0;
        $this->_postponed_errors = array();

        set_error_handler('ErrorManager_errorHandler');
    }

    /**
     * Get mask indicating which errors are currently being postponed.
     * @return int The current postponed error mask.
     */
    public function getPostponedErrorMask()
    {
        return $this->_postpone_mask;
    }

    /**
     * Set mask indicating which errors to postpone.
     *
     * The default value of the postpone mask is zero (no errors postponed.)
     *
     * When you set this mask, any queue errors which do not match the new
     * mask are reported.
     *
     * @param $newmask int The new value for the mask.
     */
    public function setPostponedErrorMask($newmask)
    {
        $this->_postpone_mask = $newmask;
        if (function_exists('PrintXML'))
            PrintXML($this->_flush_errors($newmask));
        else
            echo($this->_flush_errors($newmask));
    }

    /**
     * Report any queued error messages.
     */
    public function flushPostponedErrors()
    {
        if (function_exists('PrintXML'))
            PrintXML($this->_flush_errors());
        else
            echo $this->_flush_errors();
    }

    /**
     * Get rid of all pending error messages in case of all non-html
     * - pdf or image - output.
     */
    public function destroyPostponedErrors()
    {
        $this->_postponed_errors = array();
    }

    /**
     * Get postponed errors, formatted as HTML.
     *
     * This also flushes the postponed error queue.
     *
     * @return object HTML describing any queued errors (or false, if none).
     */
    function getPostponedErrorsAsHTML()
    {
        $flushed = $this->_flush_errors();
        if (!$flushed)
            return false;
        if ($flushed->isEmpty())
            return false;
        // format it with the worst class (error, warning, notice)
        $worst_err = $flushed->_content[0];
        foreach ($flushed->_content as $err) {
            if ($err and is_a($err, 'PhpError') and $err->errno > $worst_err->errno) {
                $worst_err = $err;
            }
        }
        if ($worst_err->isNotice())
            return $flushed;
        $class = $worst_err->getHtmlClass();
        $html = HTML::div(array('class' => $class),
            HTML::div(array('class' => 'errors'),
                "PHP " . $worst_err->getDescription()));
        $html->pushContent($flushed);
        return $html;
    }

    /**
     * Push a custom error handler on the handler stack.
     *
     * Sometimes one is performing an operation where one expects
     * certain errors or warnings. In this case, one might not want
     * these errors reported in the normal manner. Installing a custom
     * error handler via this method allows one to intercept such
     * errors.
     *
     * An error handler installed via this method should be either a
     * function or an object method taking one argument: a PhpError
     * object.
     *
     * The error handler should return either:
     * <dl>
     * <dt> False <dd> If it has not handled the error. In this case,
     *                 error processing will proceed as if the handler
     *                 had never been called: the error will be passed
     *                 to the next handler in the stack, or the
     *                 default handler, if there are no more handlers
     *                 in the stack.
     *
     * <dt> True <dd> If the handler has handled the error. If the
     *                error was a non-fatal one, no further processing
     *                will be done. If it was a fatal error, the
     *                ErrorManager will still terminate the PHP
     *                process (see setFatalHandler.)
     *
     * <dt> A PhpError object <dd> The error is not considered
     *                             handled, and will be passed on to
     *                             the next handler(s) in the stack
     *                             (or the default handler). The
     *                             returned PhpError need not be the
     *                             same as the one passed to the
     *                             handler. This allows the handler to
     *                             "adjust" the error message.
     * </dl>
     * @param $handler WikiCallback  Handler to call.
     */
    public function pushErrorHandler($handler)
    {
        array_unshift($this->_handlers, $handler);
    }

    /**
     * Pop an error handler off the handler stack.
     */
    public function popErrorHandler()
    {
        return array_shift($this->_handlers);
    }

    /**
     * Set a termination handler.
     *
     * This handler will be called upon fatal errors. The handler
     * gets passed one argument: a PhpError object describing the
     * fatal error.
     *
     * @param $handler WikiCallback  Callback to call on fatal errors.
     */
    public function setFatalHandler($handler)
    {
        $this->_fatal_handler = $handler;
    }

    /**
     * Handle an error.
     *
     * The error is passed through any registered error handlers, and
     * then either reported or postponed.
     *
     * @param $error object A PhpError object.
     */
    public function handleError($error)
    {
        static $in_handler;

        if (!empty($in_handler)) {
            $msg = $error->_getDetail();
            $msg->unshiftContent(HTML::h2(fmt("%s: error while handling error:",
                "ErrorManager")));
            $msg->printXML();
            return;
        }

        // template which flushed the pending errors already handled,
        // so display now all errors directly.
        if (!empty($GLOBALS['request']->_finishing)) {
            $this->_postpone_mask = 0;
        }

        $in_handler = true;

        foreach ($this->_handlers as $handler) {
            if (!$handler) continue;
            $result = $handler->call($error);
            if (!$result) {
                continue; // Handler did not handle error.
            } elseif (is_object($result)) {
                // handler filtered the result. Still should pass to
                // the rest of the chain.
                if ($error->isFatal()) {
                    // Don't let handlers make fatal errors non-fatal.
                    $result->errno = $error->errno;
                }
                $error = $result;
            } else {
                // Handler handled error.
                if (!$error->isFatal()) {
                    $in_handler = false;
                    return;
                }
                break;
            }
        }

        // Error was either fatal, or was not handled by a handler.
        // Handle it ourself.
        if ($error->isFatal()) {
            $this->_noCacheHeaders();
            echo "<!DOCTYPE html>\n";
            echo '<html xml:lang="en" lang="en">'."\n";
            echo "<head>\n";
            echo "<meta charset=\"UTF-8\" />\n";
            echo "<title>"._('Fatal PhpWiki Error')."</title>\n";
            echo '<link rel="stylesheet" type="text/css" href="themes/default/phpwiki.css" />'."\n";
            echo "</head>\n";
            echo "<body>\n";
            echo '<div style="font-weight:bold; color:red;">';
            echo _('Fatal PhpWiki Error')._(':');
            echo "</div>\n";

            if (defined('DEBUG') and (DEBUG & _DEBUG_TRACE)) {
                echo "error_reporting=", error_reporting(), "\n<br />";
                $error->printSimpleTrace(debug_backtrace());
            }
            $this->_die($error);
        } elseif (($error->errno & error_reporting()) != 0) {
            if (($error->errno & $this->_postpone_mask) != 0) {
                if (is_a($error, 'PhpErrorOnce')) {
                    $error->removeDoublettes($this->_postponed_errors);
                    if ($error->_count < 2)
                        $this->_postponed_errors[] = $error;
                } else {
                    $this->_postponed_errors[] = $error;
                }
            } else {
                //echo "postponed errors: ";
                $this->_noCacheHeaders();
                if (defined('DEBUG') and (DEBUG & _DEBUG_TRACE)) {
                    echo "error_reporting=", error_reporting(), "\n";
                    $error->printSimpleTrace(debug_backtrace());
                }
                $error->printXML();
            }
        }
        $in_handler = false;
    }

    function warning($msg, $errno = E_USER_NOTICE)
    {
        $this->handleError(new PhpWikiError($errno, $msg, '?', '?'));
    }

    private function _die($error)
    {
        global $WikiTheme;
        $error->printXML();
        PrintXML($this->_flush_errors());
        if ($this->_fatal_handler)
            $this->_fatal_handler->call($error);
        if (!$WikiTheme->DUMP_MODE) {
            exit();
        }
    }

    private function _flush_errors($keep_mask = 0)
    {
        $errors = &$this->_postponed_errors;
        if (empty($errors)) return '';
        $flushed = HTML();
        for ($i = 0; $i < count($errors); $i++) {
            $error =& $errors[$i];
            if (!is_object($error)) {
                continue;
            }
            if (($error->errno & $keep_mask) != 0)
                continue;
            unset($errors[$i]);
            $flushed->pushContent($error);
        }
        return $flushed;
    }

    function _noCacheHeaders()
    {
        global $request;
        static $already = false;

        if (isset($request) and isset($request->_validators)) {
            $request->_validators->_tag = false;
            $request->_validators->_mtime = false;
        }
        if ($already) return;

        // FIXME: Howto announce that to Request->cacheControl()?
        if (!headers_sent()) {
            header("Cache-control: no-cache");
            header("Pragma: nocache");
        }
        $already = true;
    }
}

/**
 * Global error handler for class ErrorManager.
 *
 * This is necessary since PHP's set_error_handler() does not allow
 * one to set an object method as a handler.
 *
 * @access private
 */
function ErrorManager_errorHandler($errno, $errstr, $errfile, $errline)
{
    // TODO: Temporary hack to have errors displayed on dev machines.
    if (defined('DEBUG') and DEBUG and $errno < 2048) {
        print "<br/>PhpWiki Warning: ($errno, $errstr, $errfile, $errline)";
    }

    if (!isset($GLOBALS['ErrorManager'])) {
        $GLOBALS['ErrorManager'] = new ErrorManager();
    }

    if (defined('DEBUG') and DEBUG) {
        $error = new PhpError($errno, $errstr, $errfile, $errline);
    } else {
        $error = new PhpErrorOnce($errno, $errstr, $errfile, $errline);
    }
    $GLOBALS['ErrorManager']->handleError($error);
}

/**
 * A class representing a PHP error report.
 *
 * @see The PHP documentation for set_error_handler at
 *      http://php.net/manual/en/function.set-error-handler.php .
 */
class PhpError
{
    /**
     * The PHP errno
     */
    public $errno;

    /**
     * The PHP error message.
     */
    public $errstr;

    /**
     * The source file where the error occurred.
     */
    public $errfile;

    /**
     * The line number (in $this->errfile) where the error occured.
     */
    public $errline;

    /**
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    function __construct($errno, $errstr, $errfile, $errline)
    {
        $this->errno = $errno;
        $this->errstr = $errstr;
        $this->errfile = $errfile;
        $this->errline = $errline;
    }

    /**
     * Determine whether this is a fatal error.
     * @return boolean True if this is a fatal error.
     */
    function isFatal()
    {
        return ($this->errno & (2048 | EM_WARNING_ERRORS | EM_NOTICE_ERRORS)) == 0;
    }

    /**
     * Determine whether this is a warning level error.
     * @return boolean
     */
    function isWarning()
    {
        return ($this->errno & EM_WARNING_ERRORS) != 0;
    }

    /**
     * Determine whether this is a notice level error.
     * @return boolean
     */
    function isNotice()
    {
        return ($this->errno & EM_NOTICE_ERRORS) != 0;
    }

    function getHtmlClass()
    {
        if ($this->isNotice()) {
            return 'hint';
        } elseif ($this->isWarning()) {
            return 'warning';
        } else {
            return 'errors';
        }
    }

    function getDescription()
    {
        if ($this->isNotice()) {
            return 'Notice';
        } elseif ($this->isWarning()) {
            return 'Warning';
        } else {
            return 'Error';
        }
    }

    /**
     * Get a printable, HTML, message detailing this error.
     * @return object The detailed error message.
     */
    function _getDetail()
    {
        $dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR : substr(dirname(__FILE__), 0, -4);
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $dir = str_replace('/', '\\', $dir);
            $this->errfile = str_replace('/', '\\', $this->errfile);
            $dir .= "\\";
        } else
            $dir .= '/';
        $errfile = preg_replace('|^' . preg_quote($dir, '|') . '|', '', $this->errfile);
        $lines = explode("\n", $this->errstr);
        if (DEBUG & _DEBUG_VERBOSE) {
            $msg = sprintf("%s:%d %s[%d]: %s",
                $errfile, $this->errline,
                $this->getDescription(), $this->errno,
                array_shift($lines));
        } /* elseif (! $this->isFatal()) {
          $msg = sprintf("%s:%d %s: \"%s\"",
                         $errfile, $this->errline,
                         $this->getDescription(),
                         array_shift($lines));
        }*/ else {
            $msg = sprintf("%s:%d %s: \"%s\"",
                $errfile, $this->errline,
                $this->getDescription(),
                array_shift($lines));
        }

        $html = HTML::div(array('class' => $this->getHtmlClass()), HTML::p($msg));
        // The class is now used for the div container.
        // $html = HTML::div(HTML::p($msg));
        if ($lines) {
            $list = HTML::ul();
            foreach ($lines as $line)
                $list->pushContent(HTML::li($line));
            $html->pushContent($list);
        }

        return $html;
    }

    /**
     * Print an HTMLified version of this error.
     * @see asXML()
     */
    function printXML()
    {
        PrintXML($this->_getDetail());
    }

    /**
     * Return an HTMLified version of this error.
     */
    function asXML()
    {
        return AsXML($this->_getDetail());
    }

    /**
     * Return a plain-text version of this error.
     */
    function asString()
    {
        return AsString($this->_getDetail());
    }

    function printSimpleTrace($bt)
    {
        $nl = isset($_SERVER['REQUEST_METHOD']) ? "<br />" : "\n";
        echo $nl . "Traceback:" . $nl;
        foreach ($bt as $i => $elem) {
            if (!array_key_exists('file', $elem)) {
                continue;
            }
            print "  " . $elem['file'] . ':' . $elem['line'] . $nl;
        }
        flush();
    }
}

/**
 * A class representing a PhpWiki warning.
 *
 * This is essentially the same as a PhpError, except that the
 * error message is quieter: no source line, etc...
 */
class PhpWikiError extends PhpError
{
    /**
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    function __construct($errno, $errstr, $errfile, $errline)
    {
        parent::__construct($errno, $errstr, $errfile, $errline);
    }

    function _getDetail()
    {
        return HTML::div(array('class' => $this->getHtmlClass()),
            HTML::p($this->getDescription() . ": $this->errstr"));
    }
}

/**
 * A class representing a Php warning, printed only the first time.
 *
 * Similar to PhpError, except only the first same error message is printed,
 * with number of occurences.
 */
class PhpErrorOnce extends PhpError
{
    function __construct($errno, $errstr, $errfile, $errline)
    {
        $this->_count = 1;
        parent::__construct($errno, $errstr, $errfile, $errline);
    }

    function _sameError($error)
    {
        if (!$error) return false;
        return ($this->errno == $error->errno and
            $this->errfile == $error->errfile and
                $this->errline == $error->errline);
    }

    // count similar handlers, increase _count and remove the rest
    function removeDoublettes(&$errors)
    {
        for ($i = 0; $i < count($errors); $i++) {
            if (!isset($errors[$i])) continue;
            if ($this->_sameError($errors[$i])) {
                $errors[$i]->_count++;
                $this->_count++;
                if ($i) unset($errors[$i]);
            }
        }
        return $this->_count;
    }

    function _getDetail($count = 0)
    {
        if (!$count) $count = $this->_count;
        $dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR : substr(dirname(__FILE__), 0, -4);
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $dir = str_replace('/', '\\', $dir);
            $this->errfile = str_replace('/', '\\', $this->errfile);
            $dir .= "\\";
        } else
            $dir .= '/';
        $errfile = preg_replace('|^' . preg_quote($dir, '|') . '|', '', $this->errfile);
        if (is_string($this->errstr))
            $lines = explode("\n", $this->errstr);
        elseif (is_object($this->errstr))
            $lines = array($this->errstr->asXML());
        $errtype = sprintf("%s", $this->getDescription());
        if ($this->isFatal()) {
            $msg = sprintf("%s:%d %s: %s %s",
                $errfile, $this->errline,
                $errtype,
                array_shift($lines),
                $count > 1 ? sprintf(" (...repeated %d times)", $count) : ""
            );
        } else {
            $msg = sprintf("%s: \"%s\" %s",
                $errtype,
                array_shift($lines),
                $count > 1 ? sprintf(" (...repeated %d times)", $count) : "");
        }
        $html = HTML::div(array('class' => $this->getHtmlClass()), HTML::p($msg));
        if ($lines) {
            $list = HTML::ul();
            foreach ($lines as $line)
                $list->pushContent(HTML::li($line));
            $html->pushContent($list);
        }

        return $html;
    }
}

require_once(dirname(__FILE__) . '/HtmlElement.php');

if (!isset($GLOBALS['ErrorManager'])) {
    $GLOBALS['ErrorManager'] = new ErrorManager();
}
