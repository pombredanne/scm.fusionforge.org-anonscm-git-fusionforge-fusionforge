<?php
/*
 * Copyright © 2005,2007 $ThePhpWikiProgrammingTeam
 * Copyright © 2008-2009 Marc-Etienne Vargenau, Alcatel-Lucent
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
 * This plugin displays the version, date, size, perms of an uploaded file.
 * Only files relative and below to the uploads path can be handled.
 *
 * Usage:
 *   <<FileInfo file=Upload:setup.exe display=version,date >>
 *   <<FileInfo file=Upload:setup.exe display=name,version,date
 *                     format="%s (version: %s, date: %s)" >>
 *
 * @author: Reini Urban
 */

class WikiPlugin_FileInfo
    extends WikiPlugin
{
    function getDescription()
    {
        return _("Display file information like version, size, date... of uploaded files.");
    }

    function getDefaultArguments()
    {
        return array(
            'file' => false, // relative path from PHPWIKI_DIR. (required)
            'display' => false, // version,phonysize,size,date,mtime,owner,name,path,dirname,link.  (required)
            'format' => false, // printf format string with %s only, all display modes
            'quiet' => false // print no error if file not found
            // from above vars return strings (optional)
        );
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
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (!$file) {
            return $this->error(sprintf(_("A required argument “%s” is missing."), 'file'));
        }
        if (!$display) {
            return $this->error(sprintf(_("A required argument “%s” is missing."), 'display'));
        }
        if (string_starts_with($file, "Upload:")) {
            $file = preg_replace("/^Upload:(.*)$/", getUploadFilePath() . "\\1", $file);
            $is_Upload = 1;
        }
        $dir = getcwd();
        if (defined('PHPWIKI_DIR')) {
            chdir(PHPWIKI_DIR);
        }
        if (!file_exists($file)) {
            if ($quiet) {
                return HTML::raw('');
            } else {
                return $this->error(sprintf(_("File “%s” not found."), $file));
            }
        }
        // sanify $file name
        $realfile = realpath($file);
        // Hmm, allow ADMIN to check a local file? Only if its locked
        if (string_starts_with($realfile, realpath(getUploadDataPath()))) {
            $isuploaded = 1;
        } else {
            $page = $dbi->getPage($basepage);
            $user = $request->getUser();
            if ($page->getOwner() != ADMIN_USER or !$page->get('locked')) {
                // For convenience we warn the admin
                if ($quiet and $user->isAdmin())
                    return HTML::span(array('title' => _("Output suppressed. FileInfoPlugin with local files require a locked page.")),
                        HTML::em(_("page not locked")));
                else
                    return $this->error("Invalid path \"$file\". Only ADMIN can allow local paths, and the page must be locked.");
            }
        }
        $s = array();
        $modes = explode(",", $display);
        foreach ($modes as $mode) {
            switch ($mode) {
                case 'version':
                    $s[] = $this->exeversion($file);
                    break;
                case 'size':
                    $s[] = filesize($file);
                    break;
                case 'phonysize':
                    $s[] = $this->phonysize(filesize($file));
                    break;
                case 'date':
                    $s[] = strftime("%x %X", filemtime($file));
                    break;
                case 'mtime':
                    $s[] = filemtime($file);
                    break;
                case 'owner':
                    $o = posix_getpwuid(fileowner($file));
                    $s[] = $o['name'];
                    break;
                case 'group':
                    $o = posix_getgrgid(filegroup($file));
                    $s[] = $o['name'];
                    break;
                case 'name':
                    $s[] = basename($file);
                    break;
                case 'path':
                    $s[] = $file;
                    break;
                case 'dirname':
                    $s[] = dirname($file);
                    break;
                case 'magic':
                    $s[] = $this->magic($file);
                    break;
                case 'mime-typ':
                    $s[] = $this->mime_type($file);
                    break;
                case 'link':
                    if ($is_Upload) {
                        $s[] = " [" . $args['file'] . "]";
                    } elseif ($isuploaded) {
                        // will fail with user uploads
                        $s[] = " [Upload:" . basename($file) . "]";
                    } else {
                        $s[] = " [" . basename($file) . "] ";
                    }
                    break;
                default:
                    if (!$quiet) {
                        return $this->error(sprintf(_("Unsupported argument: %s=%s"), 'display', $mode));
                    } else {
                        return HTML::raw('');
                    }
                    break;
            }
        }
        chdir($dir);
        if (!$format) {
            $format = '';
            foreach ($s as $x) {
                $format .= " %s";
            }
        }
        array_unshift($s, $format);
        // $x, array($i,$j) => sprintf($x, $i, $j)
        $result = call_user_func_array("sprintf", $s);
        if (in_array('link', $modes)) {
            require_once 'lib/InlineParser.php';
            return TransformInline($result, $basepage);
        } else {
            return HTML::raw($result);
        }
    }

    function magic($file)
    {
        // Valid finfo_open (i.e. libmagic) options:
        // FILEINFO_NONE | FILEINFO_SYMLINK | FILEINFO_MIME | FILEINFO_COMPRESS | FILEINFO_DEVICES |
        // FILEINFO_CONTINUE | FILEINFO_PRESERVE_ATIME | FILEINFO_RAW
        $f = finfo_open( /*FILEINFO_MIME*/);
        $result = finfo_file(realpath($file));
        finfo_close($res);
        return $result;
    }

    function mime_type($file)
    {
        return '';
    }

    private function _formatsize($n, $factor, $suffix = '')
    {
        if ($n > $factor) {
            $b = $n / $factor;
            $n -= floor($factor * $b);
            return number_format($b, $n ? 3 : 0) . $suffix;
        }
        return '';
    }

    function phonysize($a)
    {
        $factor = 1024 * 1024 * 1000;
        if ($a > $factor)
            return $this->_formatsize($a, $factor, ' GB');
        $factor = 1024 * 1000;
        if ($a > $factor)
            return $this->_formatsize($a, $factor, ' MB');
        $factor = 1024;
        if ($a > $factor)
            return $this->_formatsize($a, $factor, ' KB');
        if ($a > 1)
            return $this->_formatsize($a, 1, ' byte');
        else
            return $a;
    }

    function exeversion($file)
    {
        if (!isWindows()) return "?";
        if (class_exists('ffi') or loadPhpExtension('ffi'))
            return $this->exeversion_ffi($file);
        if (function_exists('res_list_type') or loadPhpExtension('win32std'))
            return $this->exeversion_resopen($file);
        return exeversion_showver($file);
    }

    // http://www.codeproject.com/dll/showver.asp
    function exeversion_showver($file)
    {
        $path = realpath($file);
        $result = `showver $path`;
        return "?";
    }

    function exeversion_ffi($file)
    {
        if (!DEBUG)
            return "?"; // not yet stable

        if (function_exists('ffi') or loadPhpExtension('ffi')) {
            $win32_idl = "
struct VS_FIXEDFILEINFO {
        DWORD dwSignature;
        DWORD dwStrucVersion;
        DWORD dwFileVersionMS;
        DWORD dwFileVersionLS;
        DWORD dwProductVersionMS;
        DWORD dwProductVersionLS;
        DWORD dwFileFlagsMask;
        DWORD dwFileFlags;
        DWORD dwFileOS;
        DWORD dwFileType;
        DWORD dwFileSubtype;
        DWORD dwFileDateMS;
        DWORD dwFileDateLS;
};
struct VS_VERSIONINFO { struct VS_VERSIONINFO
  WORD  wLength;
  WORD  wValueLength;
  WORD  wType;
  WCHAR szKey[1];
  WORD  Padding1[1];
  VS_FIXEDFILEINFO Value;
  WORD  Padding2[1];
  WORD  Children[1];
};
[lib='kernel32.dll'] DWORD GetFileVersionInfoSizeA(char *szFileName, DWORD *dwVerHnd);
[lib='kernel32.dll'] int GetFileVersionInfoA(char *sfnFile, DWORD dummy, DWORD size, struct VS_VERSIONINFO *pVer);
";
            $ffi = new ffi($win32_idl);
            $dummy = 0; // &DWORD
            $size = $ffi->GetFileVersionInfoSizeA($file, $dummy);
            //$pVer = str_repeat($size+1);
            $pVer = new ffi_struct($ffi, "VS_VERSIONINFO");
            if ($ffi->GetFileVersionInfoA($file, 0, $size, $pVer)
                and $pVer->wValueLength
            ) {
                // analyze the VS_FIXEDFILEINFO(Value);
                // $pValue = new ffi_struct($ffi, "VS_FIXEDFILEINFO");
                $pValue =& $pVer->Value;
                return sprintf("%d.%d.%d.%d",
                    $pValue->dwFileVersionMS >> 16,
                    $pValue->dwFileVersionMS & 0xFFFF,
                    $pValue->dwFileVersionLS >> 16,
                    $pValue->dwFileVersionLS & 0xFFFF);
            }
        }
        return '';
    }

    // Read "RT_VERSION/VERSIONINFO" exe/dll resource info for MSWin32 binaries
    // The "win32std" extension is not ready yet to pass back a VERSIONINFO struct
    function exeversion_resopen($file)
    {
        if (function_exists('res_list_type') or loadPhpExtension('win32std')) {
            // See http://msdn.microsoft.com/workshop/networking/predefined/res.asp
            $v = file_get_contents('res://' . realpath($file) . urlencode('/RT_VERSION/#1'));
            if ($v) {
                // This is really a binary VERSIONINFO block, with lots of
                // nul bytes (widechar) which cannot be transported as string.
                return "$v";
            } else {
                $h = res_open(realpath($file));
                $v = res_get($h, 'RT_VERSION', 'FileVersion');
                res_close($h);
                if ($v) return $v;

                $h = res_open(realpath($file));
                $v = res_get($h, '#1', 'RT_VERSION', 1);
                res_close($h);
                if ($v) return $v;
            }

            /* The version consists of two 32-bit integers, defined by four 16-bit integers.
               For example, "FILEVERSION 3,10,0,61" is translated into two doublewords:
               0x0003000a and 0x0000003d, in that order. */
            /*
                    $h = res_open(realpath($file));

                    echo "Res list of '$file': \n";
                    $list= res_list_type($h, true);
                    if( $list===FALSE ) err( "Can't list type" );

                    for( $i= 0; $i<count($list); $i++ ) {
                            echo $list[$i]."\n";
                            $res= res_list($h, $list[$i]);
                            for( $j= 0; $j<count($res); $j++ ) {
                                    echo "\t".$res[$j]."\n";
                            }
                    }
                    echo "Res get: ".res_get( $h, 'A_TYPE', 'A_RC_NAME' )."\n\n";
                    res_close( $h );
            */
            if ($v)
                return "$v";
            else
                return "";
        } else {
            return "";
        }

    }
}
