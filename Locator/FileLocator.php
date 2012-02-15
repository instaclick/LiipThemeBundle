<?php

/*
 * This file is part of the Liip/ThemeBundle
 *
 * (c) Liip AG
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Liip\ThemeBundle\Locator;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Config\FileLocator as BaseFileLocator;

use Liip\ThemeBundle\ActiveTheme;

/**
 * FileLocator uses the HttpKernel FileLocator to locate resources in bundles
 * and follow a configurable file path.
 *
 * @author Tobias Ebnöther <ebi@liip.ch>
 * @author Roland Schilter <roland.schilter@liip.ch>
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 */
class FileLocator extends BaseFileLocator
{
    protected $kernel;
    protected $path;
    protected $basePaths = array();

    /**
     * @var ActiveTheme
     */
    protected $activeTheme;

    /**
     * @var string
     */
    protected $lastTheme;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     * @param string $path The path the global resource directory
     *
     * @throws \InvalidArgumentException if the active theme is not in the themes list
     */
    public function __construct(KernelInterface $kernel, ActiveTheme $activeTheme, $path = null, array $paths = array())
    {
        $this->kernel = $kernel;
        $this->activeTheme = $activeTheme;
        $this->path = $path;
        $this->basePaths = $paths;

        $this->setCurrentTheme($this->activeTheme->getName());
    }

    /**
     * Set the active theme.
     *
     * @param string $theme
     */
    public function setCurrentTheme($theme)
    {
        $this->lastTheme = $theme;

        $paths = $this->basePaths;

        // add active theme as Resources/themes/views folder as well.
        $paths[] = $this->path . '/themes/' . $theme;
        $paths[] = $this->path;

        $this->paths = $paths;
    }

    /**
     * Returns the file path for a given resource for the first directory it
     * has a match.
     *
     * The resource name must follow the following pattern:
     *
     *     @BundleName/path/to/a/file.something
     *
     * where BundleName is the name of the bundle
     * and the remaining part is the relative path in the bundle.
     *
     * @param string  $name  A resource name to locate
     * @param string  $dir   A directory where to look for the resource first
     * @param Boolean $first Whether to return the first path or paths for all matching bundles
     *
     * @return string|array The absolute path of the resource or an array if $first is false
     *
     * @throws \InvalidArgumentException if the file cannot be found or the name is not valid
     * @throws \RuntimeException         if the name contains invalid/unsafe characters
     */
    public function locate($name, $dir = null, $first = true)
    {
        // update the paths if the theme changed since the last lookup
        $theme = $this->activeTheme->getName();
        if ($this->lastTheme !== $theme) {
            $this->setCurrentTheme($theme);
        }

        if ('@' === $name[0]) {
            return $this->locateResource($name, $this->path, $first);
        }

        if (0 === strpos($name, 'views/')) {
            return $this->locateAppResource($name, $this->path, $first);
        }

        return parent::locate($name, $dir, $first);
    }

    /**
     * Locate theme in app/Resources/themes/.
     *
     * @param string $name
     * @param string $dir
     * @param bool $first
     * @return string
     */
    public function locateAppResource($name, $dir = null, $first = true)
    {
        $path = $dir.'/themes/'.$this->lastTheme.'/'.substr($name, 6);
        if (file_exists($path)) {
            return $path;
        }

        return parent::locate($name, $dir, $first);
    }

    /**
     * Locate Resource Theme aware. Only working for resources!
     *
     * Method inlined from Symfony\Component\Http\Kernel
     *
     * @param string $name
     * @param string $dir
     * @param bool $first
     * @return string
     */
    public function locateResource($name, $dir = null, $first = true)
    {
        if (false !== strpos($name, '..')) {
            throw new \RuntimeException(sprintf('File name "%s" contains invalid characters (..).', $name));
        }

        $bundleName = substr($name, 1);
        $path = '';
        if (false !== strpos($bundleName, '/')) {
            list($bundleName, $path) = explode('/', $bundleName, 2);
        }

        if (0 !== strpos($path, 'Resources')) {
            throw new \RuntimeException('Template files have to be in Resources.');
        }

        $overridePath = substr($path, 9);
        $subPath = substr($path, 15);
        $resourceBundle = null;
        $bundles = $this->kernel->getBundle($bundleName, false);
        $files = array();

        foreach ($bundles as $bundle) {
            $checkPaths = array();
            if ($dir) {
                $checkPaths[] = $dir.'/themes/'.$this->lastTheme.'/'.$bundle->getName().$overridePath;
                $checkPaths[] = $dir.'/'.$bundle->getName().$overridePath;
            }

            $checkPaths[] = $bundle->getPath().'/Resources/themes/'.$this->lastTheme.$subPath;

            foreach ($checkPaths as $checkPath) {
                if (file_exists($checkPath)) {
                    if (null !== $resourceBundle) {
                        throw new \RuntimeException(sprintf('"%s" resource is hidden by a resource from the "%s" derived bundle. Create a "%s" file to override the bundle resource.',
                            $path,
                            $resourceBundle,
                            $checkPath
                        ));
                    }

                    if ($first) {
                        return $checkPath;
                    }
                    $files[] = $checkPath;
                }
            }

            $file = $bundle->getPath().'/'.$path;
            if (file_exists($file)) {
                if ($first) {
                    return $file;
                }
                $files[] = $file;
                $resourceBundle = $bundle->getName();
            }
        }

        if (!$first && count($files)) {
            return $files;
        }

        throw new \InvalidArgumentException(sprintf('Unable to find file "%s".', $name));
    }
}
