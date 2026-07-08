<?php
/* Copyright (C) 2026
 *
 * Internal loader helpers for customized TakePOS deployments.
 * This keeps local custom files preferred while preserving fallback
 * to the core takepos module path when a file was not overridden.
 */

if (!function_exists('takeposResolveModuleFilePath')) {
	/**
	 * Resolve a TakePOS module file, preferring the current customized package.
	 *
	 * @param string      $relativePath Relative path from the takepos module root.
	 * @param string|null $moduleRoot   Optional module root path. Defaults to current package root.
	 * @return string
	 */
	function takeposResolveModuleFilePath($relativePath, $moduleRoot = null)
	{
		$moduleRoot = $moduleRoot ?: dirname(__DIR__);
		$relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
		$localPath = rtrim((string) $moduleRoot, '/\\').'/'.$relativePath;

		if (is_file($localPath)) {
			return $localPath;
		}

		return rtrim((string) DOL_DOCUMENT_ROOT, '/\\').'/takepos/'.$relativePath;
	}
}

if (!function_exists('takeposRequireModuleFile')) {
	/**
	 * require_once a TakePOS module file with local-first fallback.
	 *
	 * @param string      $relativePath Relative path from the takepos module root.
	 * @param string|null $moduleRoot   Optional module root path. Defaults to current package root.
	 * @return void
	 */
	function takeposRequireModuleFile($relativePath, $moduleRoot = null)
	{
		require_once takeposResolveModuleFilePath($relativePath, $moduleRoot);
	}
}

if (!function_exists('takeposIncludeModuleFile')) {
	/**
	 * include_once a TakePOS module file with local-first fallback.
	 *
	 * @param string      $relativePath Relative path from the takepos module root.
	 * @param string|null $moduleRoot   Optional module root path. Defaults to current package root.
	 * @return void
	 */
	function takeposIncludeModuleFile($relativePath, $moduleRoot = null)
	{
		include_once takeposResolveModuleFilePath($relativePath, $moduleRoot);
	}
}
