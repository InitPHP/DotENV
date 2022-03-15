<?php
/**
 * Dotenv.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Dotenv;

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

class Exception extends \Exception
{
}

final class Library
{

    protected array $ENV = [];

    /**
     * Bir .env dosyasını işler.
     *
     * @param string $path
     * @param bool $debug <p>Eğer dosya bulunamaz ya da okunamaz ise istisna fırlatma davranışını değiştirir.</p>
     * @return void
     * @throws Exception
     */
    public function create(string $path, bool $debug = true): void
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
    public function get(string $name, $default = null)
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
    public function env(string $name, $default = null)
    {
        return $this->get($name, $default);
    }

    private function createImmutable(string $path, bool $debug = true): void
    {
        if(is_dir($path)){
            if(($path = $this->getDirFilePath($path)) === null){
                if($debug !== FALSE){
                    throw new Exception('The file ".env" or ".env.php" could not be found in the directory you specified.');
                }
                return;
            }
        }
        if(!is_file($path)){
            if($debug !== FALSE){
                throw new Exception('The ' . $path . ' file could not be found.');
            }
            return;
        }
        $basename = basename($path);
        if($basename != '.env' && $basename != '.env.php'){
            if($debug !== FALSE){
                throw new Exception('The file to be uploaded must be ".env" or ".env.php" file.');
            }
            return;
        }
        if($basename == '.env'){
            $immutable = [];
            if(($read = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) === FALSE){
                if($debug !== FALSE){
                    throw new Exception('The "' . $path . '" file could not be read.');
                }
                return;
            }
            foreach ($read as $line) {
                $line = trim($line);
                if($line == '' || $this->isCommentLine($line)){
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);

                $key = trim((string)$key);
                $value = trim((string)$value, "' \"\t\n\r\0\x0B");
                $immutable[$key] = $value;
            }
        }else{
            $immutable = $this->phpRequired($path);
            if(!is_array($immutable)){
                if($debug !== FALSE){
                    throw new Exception('The file ".env.php" should return an associative array.');
                }
                return;
            }
        }
        $this->createArrayImmutable($immutable);
    }

    private function createArrayImmutable(array $assoc)
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


    private function isCommentLine($line): bool
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

    private function getDirFilePath(string $dir): ?string
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

    private function phpRequired(string $file)
    {
        return require $file;
    }

}

/**
 * @method static void create(string $path, bool $debug = true)
 * @method static mixed get(string $name, mixed $default = null)
 * @method static mixed env(string $name, mixed $default = null)
 */
class Dotenv
{

    protected static Library $instance;

    public function __construct()
    {
    }

    protected static function getInstance(): Library
    {
        if(!isset(self::$instance)){
            self::$instance = new Library();
        }
        return self::$instance;
    }

    public function __call($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

}
