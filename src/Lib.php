<?php
/**
 * Lib.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://initphp.github.io/license.txt  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\DotENV;

use InitPHP\DotENV\Exceptions\DotENVException;

use const DIRECTORY_SEPARATOR;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

use function is_string;
use function is_array;
use function is_numeric;
use function intval;
use function floatval;
use function strtolower;
use function preg_match;
use function preg_replace_callback;
use function substr;
use function putenv;
use function getenv;
use function sprintf;
use function trim;
use function rtrim;
use function explode;
use function file;
use function is_dir;
use function basename;
use function is_file;
use function strlen;

final class Lib
{

    /** @var array */
    protected $ENV = [];

    /**
     * Bir .env dosyasını işler.
     *
     * @param string $path
     * @param bool $debug <p>Eğer dosya bulunamaz ya da okunamaz ise istisna fırlatma davranışını değiştirir.</p>
     * @return void
     * @throws DotENVException
     */
    public function create($path, $debug = true)
    {
        $this->createImmutable($path, $debug);
    }

    /**
     * Bir ENV değerini döndürür.
     *
     * @param string $name
     * @param mixed $default
     * @return string|bool|null|mixed
     */
    public function get($name, $default = null)
    {
        if(isset($this->ENV[$name])){
            return $this->ENV[$name];
        }
        if(isset($_ENV[$name])){
            return $this->ENV[$name] = $this->convert($_ENV[$name]);
        }
        if(isset($_SERVER[$name])){
            return $this->ENV[$name] = $this->convert($_SERVER[$name]);
        }
        if(($env = getenv($name)) !== FALSE){
            return $this->ENV[$name] = $this->convert($env);
        }
        return $default;
    }

    /**
     * Bir ENV değerini döndürür.
     *
     * @see get()
     * @param string $name
     * @param mixed $default
     * @return string|bool|null|mixed
     */
    public function env($name, $default = null)
    {
        return $this->get($name, $default);
    }

    /**
     * @param string $path
     * @param bool $debug
     * @return void
     */
    private function createImmutable($path, $debug = true)
    {
        if(is_dir($path)){
            if(($path = $this->getDirFilePath($path)) === null){
                if($debug !== FALSE){
                    throw new DotENVException('The file ".env" or ".env.php" could not be found in the directory you specified.');
                }
                return;
            }
        }
        if(!is_file($path)){
            if($debug !== FALSE){
                throw new DotENVException('The ' . $path . ' file could not be found.');
            }
            return;
        }
        $basename = basename($path);
        if($basename != '.env' && $basename != '.env.php'){
            if($debug !== FALSE){
                throw new DotENVException('The file to be uploaded must be ".env" or ".env.php" file.');
            }
            return;
        }
        if($basename == '.env'){
            $immutable = [];
            if(($read = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) === FALSE){
                if($debug !== FALSE){
                    throw new DotENVException('The "' . $path . '" file could not be read.');
                }
                return;
            }
            foreach ($read as $line) {
                $line = trim($line);
                if($line == '' || $this->isCommentLine($line)){
                    continue;
                }
                list($key, $value) = explode('=', $line, 2);

                $key = trim((string)$key);

                if (preg_match('/^"(.*)"/i', $value, $matches) || preg_match("/^'(.*)'/i", $value, $matches)) {
                    $value = $matches[1];
                } else {
                    if (preg_match('/[ \t]*+(?:#.*)?$/i', $value, $matches)) {
                        $value = substr($value, 0, (0 - strlen($matches[0])));
                    }
                    $value = trim($value);
                }
                $immutable[$key] = $value;
            }
        }else{
            $immutable = $this->phpRequired($path);
            if(!is_array($immutable)){
                if($debug !== FALSE){
                    throw new DotENVException('The file ".env.php" should return an associative array.');
                }
                return;
            }
        }
        $this->createArrayImmutable($immutable);
    }

    /**
     * @param array $assoc
     * @return void
     */
    private function createArrayImmutable($assoc)
    {
        foreach ($assoc as $key => $value) {
            if(!isset($_SERVER[$key]) && !isset($_ENV[$key])){
                if(is_string($value)){
                    putenv(sprintf('%s=%s', $key, $value));
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }


    /**
     * @param $line
     * @return bool
     */
    private function isCommentLine($line)
    {
        $firstChar = substr($line, 0, 1);
        return !((bool)preg_match('/^[\w-]+$/u', $firstChar));
    }

    private function convert($data)
    {
        if(!is_string($data)){
            return $data;
        }
        switch (strtolower($data)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'empty':
            case '':
                return '';
            case 'null':
                return null;
        }
        $data = $this->nestedVariables($data);
        if(is_numeric($data)){
            if((bool)preg_match('/^-?([0-9]+)$/u', $data)){
                return intval($data);
            }
            return floatval($data);
        }
        return $data;
    }

    private function nestedVariables($data)
    {
        return preg_replace_callback('/\${(.+)}/', function ($env) {
            $env = trim($env[1], " \t\n\r\0\x0B\"'");
            return (string)$this->get($env);
        }, $data);
    }

    /**
     * @param string $dir
     * @return string|null
     */
    private function getDirFilePath($dir)
    {
        $path = rtrim($dir . '\\/') . DIRECTORY_SEPARATOR;
        if(is_file(($path . '.env'))){
            $path .= '.env';
            return $path;
        }
        if(is_file($path . '.env.php')){
            $path .= '.env.php';
            return $path;
        }
        return null;
    }

    /**
     * @param string $file
     * @return array
     */
    private function phpRequired($file)
    {
        return require $file;
    }

}
