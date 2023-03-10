<?php
/**
 * Copyright © 2004 $ThePhpWikiProgrammingTeam
 * Copyright © 2008 Marc-Etienne Vargenau, Alcatel-lucent
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

/**
 * This plugin provides configurable polls.
 *
 * TODO:
 *     admin page (view and reset statistics)
 *     for now only radio, support checkboxes (multiple selections) also?
 *
 * Author: Reini Urban
 */

class WikiPlugin_WikiPoll
    extends WikiPlugin
{
    public $_args;

    function getDescription()
    {
        return _("Enable configurable polls.");
    }

    function getDefaultArguments()
    {
        return array('page' => '[pagename]',
            'admin' => false,
            'require_all' => 1, // 1 if all questions must be answered
            'require_least' => 0, // how many at least
        );
    }

    /**
     * @param string $argstr
     * @param WikiRequest $request
     * @param array $defaults
     * @return array
     */
    function getArgs($argstr, $request = null, $defaults = array())
    {
        if (empty($defaults)) {
            $defaults = $this->getDefaultArguments();
        }
        //Fixme: on POST argstr is empty
        $args = array();
        list ($argstr_args, $argstr_defaults) = $this->parseArgStr($argstr);
        if (isset($argstr_args["question_1"])) {
            $args['question'] = $this->str2array("question", $argstr_args);
            $args['answer'] = array();
            for ($i = 0; $i <= count($args['question']); $i++) {
                if ($array = $this->str2array(sprintf("%s_%d", "answer", $i), $argstr_args))
                    $args['answer'][$i] = $array;
            }
        }

        if (!empty($defaults))
            foreach ($defaults as $arg => $default_val) {
                if (isset($argstr_args[$arg]))
                    $args[$arg] = $argstr_args[$arg];
                elseif ($request and ($argval = $request->getArg($arg)) !== false)
                    $args[$arg] = $argval; elseif (isset($argstr_defaults[$arg]))
                    $args[$arg] = (string)$argstr_defaults[$arg]; else
                    $args[$arg] = $default_val;

                if ($request)
                    $args[$arg] = $this->expandArg($args[$arg], $request);

                unset($argstr_args[$arg]);
                unset($argstr_defaults[$arg]);
            }

        foreach (array_merge($argstr_args, $argstr_defaults) as $arg => $val) {
            if (!preg_match("/^(answer_|question_)/", $arg))
                trigger_error(sprintf(_("Argument “%s” not declared by plugin."), $arg));
        }

        return $args;
    }

    function handle_plugin_args_cruft($argstr, $args)
    {
        /**
         * @var WikiRequest $request
         */
        global $request;

        $argstr = str_replace("\n", " ", $argstr);
        $argstr = str_replace(array("[", "]"), array("_", ""), $argstr);
        $this->_args = $this->getArgs($argstr, $request);
    }

    private function str2array($var, $obarray = false)
    {
        if (!$obarray) $obarray = $GLOBALS;
        $i = 0;
        $array = array();
        $name = sprintf("%s_%d", $var, $i);
        if (isset($obarray[$name])) $array[$i] = $obarray[$name];
        do {
            $i++;
            $name = sprintf("%s_%d", $var, $i);
            if (isset($obarray[$name])) $array[$i] = $obarray[$name];
        } while (isset($obarray[$name]));
        return $array;
    }

    /**
     * @param WikiDB $dbi
     * @param string $argstr
     * @param WikiRequest $request
     * @param string $basepage
     * @return mixed
     */
    function run($dbi, $argstr, &$request, $basepage)
    {
        if (!isset($_SERVER))
            $_SERVER =& $GLOBALS['HTTP_SERVER_VARS'];
        $request->setArg('nocache', 'purge');
        $args = $this->getArgs($argstr, $request);
        if (!$args['page']) {
            return $this->error(sprintf(_("A required argument “%s” is missing."), 'page'));
        }
        if (!empty($args['admin']) and $request->_user->isAdmin()) {
            // reset statistics
            return $this->doPollAdmin($dbi, $request, $page);
        }
        extract($this->_args);
        $page = $dbi->getPage($args['page']);
        // check ip and last visit
        $poll = $page->get("poll");
        $ip = $_SERVER['REMOTE_ADDR'];
        $disable_submit = false;
        if (isset($poll['ip'][$ip]) and ((time() - $poll['ip'][$ip]) < 20 * 60)) {
            //view at least the result or disable the Go button
            $html = HTML::div();
            $html->pushContent(HTML::div(array('class' => 'warning'),
                _("Sorry! You must wait at least 20 minutes until you can vote again!")));
            $html->pushContent($this->doPoll($page, $request, $request->getArg('answer'), true));
            return $html;
        }

        $poll['ip'][$ip] = time();
        // purge older ip's
        foreach ($poll['ip'] as $ip => $time) {
            if ((time() - $time) > 21 * 60)
                unset($poll['ip'][$ip]);
        }
        $html = HTML::form(array('action' => $request->getPostURL(),
            'method' => 'post'));

        if ($request->isPost()) {
            // checkme: check if all answers are answered
            if ($request->getArg('answer') and (
                ($args['require_all'] and
                    count($request->getArg('answer')) == count($question))
                    or
                    ($args['require_least'] and
                        count($request->getArg('answer')) >= $args['require_least']))
            ) {
                $page->set("poll", $poll);
                // update statistics and present them the user
                return $this->doPoll($page, $request, $request->getArg('answer'));
            } else {
                $html->pushContent(HTML::div(array('class' => 'warning'), _("Not enough questions answered!")));

            }
        }

        $init = isset($question[0]) ? 0 : 1;
        for ($i = $init; $i <= count($question); $i++) {
            if (!isset($question[$i])) break;
            $q = $question[$i];
            if (!isset($answer[$i]))
                trigger_error(fmt("Missing %s for %s", "answer" . "[$i]", "question" . "[$i]"),
                    E_USER_ERROR);
            $a = $answer[$i];
            if (!is_array($a)) {
                // a simple checkbox
                $html->pushContent(HTML::p(HTML::strong($q)));
                $html->pushContent(HTML::div(
                    HTML::input(array('type' => 'checkbox',
                        'name' => "answer[$i]",
                        'value' => 1)),
                    HTML::raw("&nbsp;"), $a));
            } else {
                $row = HTML();
                for ($j = 0; $j <= count($a); $j++) {
                    if (isset($a[$j]))
                        $row->pushContent(HTML::div(
                            HTML::input(array('type' => 'radio',
                                'name' => "answer[$i]",
                                'value' => $j,
                                'id' => "answer[$i]-$j")),
                            HTML::raw("&nbsp;"),
                            HTML::label(array('for' => "answer[$i]-$j"), $a[$j])));
                }
                $html->pushContent(HTML::p(HTML::strong($q)), $row);
            }
        }
        if (!$disable_submit) {
            $html->pushContent(HTML::p(
                HTML::input(array('type' => 'submit',
                    'name' => "WikiPoll",
                    'value' => _("OK"))),
                HTML::input(array('type' => 'reset',
                    'name' => "reset",
                    'value' => _("Reset")))));
        } else {
            $html->pushContent(HTML::div(array('class' => 'warning'),
                _("Sorry! You must wait at least 20 minutes until you can vote again!")));
        }
        return $html;
    }

    private function bar($percent)
    {
        global $WikiTheme;
        return HTML(HTML::img(array('src' => $WikiTheme->getImageURL('leftbar'),
                'alt' => '<')),
            HTML::img(array('src' => $WikiTheme->getImageURL('mainbar'),
                'alt' => '-',
                'width' => sprintf("%02d", $percent),
                'height' => 14)),
            HTML::img(array('src' => $WikiTheme->getImageURL('rightbar'),
                'alt' => '>')));
    }

    private function doPoll($page, $request, $answers, $readonly = false)
    {
        $question = $this->_args['question'];
        $answer = $this->_args['answer'];
        $html = HTML::table(array('cellspacing' => 2));
        $init = isset($question[0]) ? 0 : 1;
        for ($i = $init; $i <= count($question); $i++) {
            if (!isset($question[$i])) break;
            $poll = $page->get('poll');
            @$poll['data']['all'][$i]++;
            $q = $question[$i];
            if (!isset($answer[$i]))
                trigger_error(fmt("Missing %s for %s", "answer" . "[$i]", "question" . "[$i]"),
                    E_USER_ERROR);
            if (!$readonly)
                $page->set('poll', $poll);
            $a = $answer[$i];
            $result = (isset($answers[$i])) ? $answers[$i] : -1;
            if (!is_array($a)) {
                $checkbox = HTML::input(array('type' => 'checkbox',
                    'name' => "answer[$i]",
                    'value' => $a));
                if ($result >= 0)
                    $checkbox->setAttr('checked', "checked");
                if (!$readonly)
                    list($percent, $count, $all) = $this->storeResult($page, $i, $result ? 1 : 0);
                else
                    list($percent, $count, $all) = $this->getResult($page, $i, 1);
                $print = sprintf(_("  %d%% (%d/%d)"), $percent, $count, $all);
                $html->pushContent(HTML::tr(HTML::th(array('colspan' => 4, 'class' => 'align-left'), $q)));
                $html->pushContent(HTML::tr(HTML::td($checkbox),
                    HTML::td($a),
                    HTML::td($this->bar($percent)),
                    HTML::td($print)));
            } else {
                $html->pushContent(HTML::tr(HTML::th(array('colspan' => 4, 'class' => 'align-left'), $q)));
                $row = HTML();
                if (!$readonly)
                    $this->storeResult($page, $i, $answers[$i]);
                for ($j = 0; $j <= count($a); $j++) {
                    if (isset($a[$j])) {
                        list($percent, $count, $all) = $this->getResult($page, $i, $j);
                        $print = sprintf(_("  %d%% (%d/%d)"), $percent, $count, $all);
                        $radio = HTML::input(array('type' => 'radio',
                            'name' => "answer[$i]",
                            'value' => $j));
                        if ($result == $j)
                            $radio->setAttr('checked', "checked");
                        $row->pushContent(HTML::tr(HTML::td($radio),
                            HTML::td($a[$j]),
                            HTML::td($this->bar($percent)),
                            HTML::td($print)));
                    }
                }
                $html->pushContent($row);
            }
        }
        if (!$readonly)
            return HTML(HTML::h3(_("The result of this poll so far:")), $html, HTML::p(_("Thanks for participating!")));
        else
            return HTML(HTML::h3(_("The result of this poll so far:")), $html);

    }

    private function getResult($page, $i, $j)
    {
        $poll = $page->get("poll");
        @$count = $poll['data']['count'][$i][$j];
        @$all = $poll['data']['all'][$i];
        $percent = sprintf("%d", $count * 100.0 / $all);
        return array($percent, $count, $all);
    }

    private function storeResult($page, $i, $j)
    {
        $poll = $page->get("poll");
        if (!$poll) {
            $poll = array('data' => array('count' => array(),
                'all' => array()));
        }
        @$poll['data']['count'][$i][$j]++;
        //@$poll['data']['all'][$i];
        $page->set("poll", $poll);
        $percent = sprintf("%d", $poll['data']['count'][$i][$j] * 100.0 / $poll['data']['all'][$i]);
        return array($percent, $poll['data']['count'][$i][$j], $poll['data']['all'][$i]);
    }

}
