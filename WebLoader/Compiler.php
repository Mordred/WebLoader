<?php

namespace WebLoader;

/**
 * Compiler
 *
 * @author Jan Marek
 * @author Mgr. Martin Jantošovič <martin.jantosovic@freya.sk>
 */
class Compiler
{
	const DEPENDENCIES_CACHE = '.dependencies';

	/** @var string */
	private $outputDir;

	/** @var bool */
	private $joinFiles = true;

	/** @var array */
	private $filters = array();

	/** @var array */
	private $fileFilters = array();

	/** @var IFileCollection */
	private $collection;

	/** @var IOutputNamingConvention */
	private $namingConvention;

	/** @var array */
	private $dependedFiles;

	public function __construct(IFileCollection $files, IOutputNamingConvention $convention, $outputDir)
	{
		$this->collection = $files;
		$this->namingConvention = $convention;
		$this->setOutputDir($outputDir);
	}

	/**
	 * Create compiler with predefined css output naming convention
	 * @param IFileCollection $files
	 * @param string $outputDir
	 * @return Compiler
	 */
	public static function createCssCompiler(IFileCollection $files, $outputDir)
	{
		return new static($files, DefaultOutputNamingConvention::createCssConvention(), $outputDir);
	}

	/**
	 * Create compiler with predefined javascript output naming convention
	 * @param IFileCollection $files
	 * @param string $outputDir
	 * @return Compiler
	 */
	public static function createJsCompiler(IFileCollection $files, $outputDir)
	{
		return new static($files, DefaultOutputNamingConvention::createJsConvention(), $outputDir);
	}

	/**
	 * Get temp path
	 * @return string
	 */
	public function getOutputDir()
	{
		return $this->outputDir;
	}

	/**
	 * Set temp path
	 * @param string $tempPath
	 */
	public function setOutputDir($tempPath)
	{
		$tempPath = realpath($tempPath);

		if (!is_dir($tempPath)) {
			throw new FileNotFoundException('Temp path does not exist.');
		}

		if (!is_writable($tempPath)) {
			throw new InvalidArgumentException("Directory '$tempPath' is not writeable.");
		}

		$this->outputDir = $tempPath;
	}

	/**
	 * Get join files
	 * @return bool
	 */
	public function getJoinFiles()
	{
		return $this->joinFiles;
	}

	/**
	 * Set join files
	 * @param bool $joinFiles
	 */
	public function setJoinFiles($joinFiles)
	{
		$this->joinFiles = (bool) $joinFiles;
	}

	/**
	 * Get last modified timestamp of newest file
	 * @param array $files
	 * @return int
	 */
	public function getLastModified(array $files = null)
	{
		if ($files === null) {
			$files = $this->collection->getFiles();
		}

		$modified = 0;

		foreach ($files as $file) {
			$modified = max($modified, filemtime($file));
			if ($this->dependedFiles === NULL)
				$this->getDependedFiles();
			if (isset($this->dependedFiles[$file])) {
				foreach ($this->dependedFiles[$file] as $k => $f)
					if (is_file($f))
						$modified = max($modified, filemtime($f));
					else
						unset($this->dependedFiles[$file][$k]);
			}
		}

		return $modified;
	}

	/**
	 * Get joined content of all files
	 * @param array $files
	 * @return string
	 */
	public function getContent(array $files = null)
	{
		if ($files === null) {
			$files = $this->collection->getFiles();
		}

		// load content
		$content = '';
		foreach ($files as $file) {
			$content .= $this->loadFile($file);
		}

		// apply filters
		foreach ($this->filters as $filter) {
			$content = call_user_func($filter, $content, $this);
		}

		return $content;
	}

	/**
	 * Load content and save file
	 * @param bool $ifModified
	 * @return array filenames of generated files
	 */
	public function generate($ifModified = TRUE)
	{
		if ($this->joinFiles) {
			$arr = [];
			if ($this->collection->getFiles()) {
				$before = memory_get_peak_usage();
				$generated = $this->generateFiles($this->collection->getFiles(), $ifModified);
				Nette\Diagnostics\Panel::addFile($this->collection->getFiles(),
					$this->outputDir . '/' . $generated->file,
					memory_get_peak_usage() - $before);
				$arr[] = $generated;
			}
			return $arr;
		} else {
			$arr = array();

			foreach ($this->collection->getFiles() as $file) {
				$before = memory_get_peak_usage();
				$generated = $this->generateFiles(array($file), $ifModified);
				Nette\Diagnostics\Panel::addFile($file,
					$this->outputDir . '/' . $generated->file,
					memory_get_peak_usage() - $before);
				$arr[] = $generated;
			}

			return $arr;
		}
	}

	protected function generateFiles(array $files, $ifModified)
	{
		$name = $this->namingConvention->getFilename($files, $this);
		$path = $this->outputDir . '/' . $name;
		$lastModified = $this->getLastModified($files);

		if (!$ifModified || !file_exists($path) || $lastModified > filemtime($path)) {
			$outPath = in_array('safe', stream_get_wrappers()) ? 'safe://' . $path : $path;
			file_put_contents($outPath, $this->getContent($files));
		}

		return (object) array(
			'file' => $name,
			'lastModified' => $lastModified
		);
	}

	/**
	 * Load file
	 * @param string $file path
	 * @return string
	 */
	protected function loadFile($file)
	{
		$content = file_get_contents($file);

		foreach ($this->fileFilters as $filter) {
			$content = call_user_func($filter, $content, $this, $file);
		}

		return $content;
	}

	/**
	 * @return \WebLoader\IFileCollection
	 */
	public function getFileCollection()
	{
		return $this->collection;
	}

	/**
	 * @return \WebLoader\IOutputNamingConvention
	 */
	public function getOutputNamingConvention()
	{
		return $this->namingConvention;
	}

	/**
	 * @param \WebLoader\IFileCollection $collection
	 */
	public function setFileCollection(IFileCollection $collection)
	{
		$this->collection = $collection;
	}

	/**
	 * @param \WebLoader\IOutputNamingConvention $namingConvention
	 */
	public function setOutputNamingConvention(IOutputNamingConvention $namingConvention)
	{
		$this->namingConvention = $namingConvention;
	}

	/**
	 * @param callback $filter
	 * @throws InvalidArgumentException
	 */
	public function addFilter($filter)
	{
		if (!is_callable($filter)) {
			throw new InvalidArgumentException('Filter is not callable.');
		}

		$this->filters[] = $filter;
	}

	/**
	 * @return array
	 */
	public function getFilters()
	{
		return $this->filters;
	}

	/**
	 * @param callback $filter
	 * @throws InvalidArgumentException
	 */
	public function addFileFilter($filter)
	{
		if (!is_callable($filter)) {
			throw new InvalidArgumentException('Filter is not callable.');
		}

		$this->fileFilters[] = $filter;
	}

	/**
	 * @return array
	 */
	public function getFileFilters()
	{
		return $this->fileFilters;
	}

	/**
	 * Get cached dependencies
	 *
	 * @return array Key of array is the file and value is array of dependend files
	 */
	public function getDependedFiles() {
		if ($this->dependedFiles === NULL) {
			if (is_file($this->outputDir . '/' . self::DEPENDENCIES_CACHE))
				$this->dependedFiles = unserialize(file_get_contents('safe://' . $this->outputDir . '/' . self::DEPENDENCIES_CACHE));
			else
				$this->dependedFiles = [];
		}
		return $this->dependedFiles;
	}

	/**
	 * Clear depended files cache
	 */
	public function clearDepended() {
		ulink($this->outputDir . '/' . self::DEPENDENCIES_CACHE);
		$this->dependedFiles = NULL;
	}

	/**
	 * Store depended files to the cache
	 */
	public function setDependedFiles($file, $files) {
		if ($this->dependedFiles === NULL)
			$this->getDependedFiles();

		if ($files === NULL)
			unset($this->dependedFiles[$file]);
		else
			$this->dependedFiles[$file] = $files;

		file_put_contents('safe://' . $this->outputDir . '/' . self::DEPENDENCIES_CACHE, serialize($this->dependedFiles));
	}

}
