<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 11/1/15
 * Time: 12:30 AM
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\ForbiddenFunctions\Fixer;

use NilPortugues\ForbiddenFunctions\Fixer\Interfaces\FileSystem;
use Zend\Code\Generator\FileGenerator;

class FileEditor
{
    /**
     * @var array
     */
    private static $characters = [" ", "(", ",", "!", "[", "="];

    /**
     * @var array
     */
    private static $constants = [];

    private $fileGenerator;
    private $functions;
    private $fileSystem;

    /**
     * FileEditor constructor.
     * @param FileGenerator $fileGenerator
     * @param FunctionRepository $functionRepository
     * @param FileSystem $fileSystem
     */
    public function __construct(
        FileGenerator $fileGenerator,
        FunctionRepository $functionRepository,
        FileSystem $fileSystem
    ) {
        $this->fileGenerator = $fileGenerator;
        $this->functions = $functionRepository;
        $this->fileSystem = $fileSystem;
    }

    /**
     * @param $path
     * @return float
     */
    public function addBackslashesToFunctions($path)
    {
        $generator = $this->fileGenerator->fromReflectedFileName($path);
        $source = $generator->getSourceContent();

        foreach (self::$characters as $character) {
            $functions = $this->buildFunctions($character);
            $functions = $this->removeFunctionsExistingInCurrentNamespaceFromBackslashing($generator, $functions);
            $functions = $this->removeUseFunctionsFromBackslashing($generator, $functions);
            $source = \str_replace($functions, $this->buildBackslashedFunctions($character), $source);
        }

        $source = \str_replace($this->getDefinedConstants(), $this->replaceConstants(), $source);
        $source = \str_replace(['true', 'false', 'null'], ['\true', '\false', '\null'], $source);
        $source = \str_replace("function \\", "function ", $source);
        $source = \str_replace("::\\", "::", $source);

        $this->fileSystem->writeFile($path, $source);
    }

    /**
     * If a method exists under the current namespace, delete from list and don't replace.
     *
     * @param FileGenerator $generator
     * @param array         $functions
     *
     * @return array
     */
    public function removeFunctionsExistingInCurrentNamespaceFromBackslashing(
        FileGenerator $generator,
        array $functions
    ) {
        $namespace = $generator->getNamespace();
        if (\strlen($namespace)>0) {
            foreach (\array_keys($functions) as $function) {
                $functionName = \sprintf("\\%s\\%s", \ltrim($namespace, "\\"), $function);

                if (\function_exists($functionName)) {
                    unset($functions[$function]);
                }
            }
        }

        return $functions;
    }

    /**
     * If a method exists under a namespace and has been aliased, or has been imported, don't replace.
     *
     * @param FileGenerator $generator
     * @param array         $functions
     *
     * @return array
     */
    public function removeUseFunctionsFromBackslashing(FileGenerator $generator, array $functions)
    {
        foreach ($generator->getUses() as $namespacedFunction) {
            list($functionOrClass, $alias) = $namespacedFunction;

            if (\function_exists($functionOrClass)) {
                $function = \explode("\\", $functionOrClass);
                $function = \array_pop($function);

                if (!empty($functions[$function])) {
                    unset($functions[$function]);
                }

                if (\strlen($alias) > 0 && !empty($functions[$alias])) {
                    unset($functions[$alias]);
                }
            }
        }

        return $functions;
    }

    /**
     * @param  string $previousCharacter
     * @return array
     */
    private function buildBackslashedFunctions($previousCharacter = ' ')
    {
        $backSlashedFunctions = $this->functions->getFunctions();

        $callback             = function ($v) use ($previousCharacter) {
            return $previousCharacter.'\\'.\ltrim($v, "\\")."(";
        };

        return \array_map($callback, $backSlashedFunctions);
    }

    /**
     * @param  string $previousCharacter
     * @return array
     */
    private function buildFunctions($previousCharacter = ' ')
    {
        $functions = $this->functions->getFunctions();
        $callback  = function ($v) use ($previousCharacter) {
            return $previousCharacter.$v."(";
        };
        $functions = \array_map($callback, $functions);

        return $functions;
    }

    /**
     * @return array
     */
    private function replaceConstants()
    {
        $constants = $this->getDefinedConstants();
        $callback  = function ($v) {
            return sprintf('\%s', $v);
        };

        return \array_map($callback, $constants);
    }

    /**
     * @return array
     */
    private function getDefinedConstants()
    {
        if (empty(self::$constants)) {
            self::$constants = \array_keys(\get_defined_constants(\false));
        }

        return self::$constants;
    }
}
