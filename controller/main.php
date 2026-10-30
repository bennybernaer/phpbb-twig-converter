<?php

/**
 *
 * Twig Converter. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, kasimi, https://kasimi.net
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace kasimi\twigconverter\controller;

use phpbb\exception\http_exception;
use phpbb\extension\manager;
use phpbb\filesystem\filesystem;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\symfony_request;
use phpbb\template\template;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Error\SyntaxError;
use Twig\Lexer;
use Twig\Source;

class main
{
	protected $request;
	protected $symfony_request;
	protected $template;
	protected $lexer;
	protected $language;
	protected $ext_manager;
	protected $filesystem;
	protected $root_path;
	protected $ext_name;

	public function __construct(
		request_interface $request,
		symfony_request $symfony_request,
		template $template,
		Lexer $lexer,
		language $language,
		manager $ext_manager,
		filesystem $filesystem,
		$root_path,
		$ext_name
	)
	{
		$this->request = $request;
		$this->symfony_request = $symfony_request;
		$this->template = $template;
		$this->lexer = $lexer;
		$this->language = $language;
		$this->ext_manager = $ext_manager;
		$this->filesystem = $filesystem;
		$this->root_path = $root_path;
		$this->ext_name = $ext_name;
	}

	public function convert($u_action)
	{
		$this->language->add_lang('common', $this->ext_name);

		$available_extensions = $this->available_extensions();
		$available_styles = $this->available_styles();

		add_form_key($this->ext_name);

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($this->ext_name))
			{
				trigger_error('FORM_INVALID');
			}

			$name = $this->request->variable('name', '');

			try
			{
				if (in_array($name, $available_extensions))
				{
					$this->run($name, $this->get_extension_template_files($name));
				}
				else if (in_array($name, $available_styles))
				{
					$this->run($name, $this->get_style_template_files($name));
				}
			}
			catch (http_exception $e)
			{
				$this->template->assign_var('ERROR', $this->language->lang_array($e->getMessage(), $e->get_parameters()));
			}
			catch (\Exception $e)
			{
				$this->template->assign_var('ERROR', $this->language->lang($e->getMessage()));
			}
		}

		foreach ($available_extensions as $ext_name)
		{
			$this->template->assign_block_vars('ext', ['NAME' => $ext_name]);
		}

		foreach ($available_styles as $style_name)
		{
			$this->template->assign_block_vars('style', ['NAME' => $style_name]);
		}

		$this->template->assign_var('U_ACTION', $u_action);
	}

	protected function available_extensions()
	{
		return array_keys($this->ext_manager->all_available());
	}

	protected function available_styles()
	{
		$styles = [];
		$styles_path = $this->root_path . 'styles/';

		if (!is_dir($styles_path))
		{
			return $styles;
		}

		$iterator = new \FilesystemIterator($styles_path, \FilesystemIterator::SKIP_DOTS);

		foreach ($iterator as $file)
		{
			if (!$file->isDir())
			{
				continue;
			}

			$style_path = $file->getPathname();
			$is_legacy_style = is_file($style_path . '/style.cfg');
			$is_composer_style = false;

			$composer_file = $style_path . '/composer.json';
			if (is_file($composer_file))
			{
				$composer = json_decode((string) file_get_contents($composer_file), true);
				$is_composer_style = is_array($composer)
					&& isset($composer['type'])
					&& $composer['type'] === 'phpbb-style';
			}

			if ($is_legacy_style || $is_composer_style)
			{
				$styles[] = $file->getFilename();
			}
		}

		sort($styles, SORT_NATURAL | SORT_FLAG_CASE);
		return $styles;
	}

	protected function get_extension_template_files($ext_name)
	{
		$finder = $this->ext_manager->get_finder(true);

		$template_files = $finder
			->extension_suffix('.html')
			->find_from_extension($ext_name, $this->ext_manager->get_extension_path($ext_name, true));

		$email_files = $finder
			->extension_directory('email/')
			->extension_suffix('.txt')
			->find_from_extension($ext_name, $this->ext_manager->get_extension_path($ext_name, true));

		return array_merge(array_keys($template_files), array_keys($email_files));
	}

	protected function get_style_template_files($style_name)
	{
		$template_files = $this->ext_manager->get_finder(true)
			->core_suffix('.html')
			->find_from_paths(['/' => $this->root_path . 'styles/' . $style_name]);

		$filenames = [];

		foreach ($template_files as $template_file)
		{
			if ($template_file['filename'] === 'bbcode.html')
			{
				continue;
			}

			$filenames[] = 'styles/' . $style_name . '/' . $template_file['named_path'];
		}

		return $filenames;
	}

	protected function run($name, array $template_files)
	{
		if (!$template_files)
		{
			throw new http_exception(400, 'TWIGCONVERTER_ERROR_NO_TEMPLATE_FILES');
		}

		$template_contents = $this->convert_files($template_files);
		$zip_directory = $this->root_path . 'store/';
		$zip_filename = str_replace('/', '_', $name) . '_twig_templates.zip';

		$this->make_zip($zip_directory, $zip_filename, $template_contents);
		$this->download($zip_directory . $zip_filename, 'application/zip');
		exit_handler();
	}

	protected function convert_files(array $filenames)
	{
		$converted_syntax = [];

		foreach ($filenames as $filename)
		{
			$contents = @file_get_contents($this->root_path . $filename);

			if (phpbb_version_compare(PHPBB_VERSION, '3.3.0@dev', '<'))
			{
				$source = $this->lexer->tokenize($contents, $filename)->getSourceContext();
			}
			else
			{
				$source = $this->lexer->tokenize(new Source($contents, $filename))->getSourceContext();
			}

			$converted_syntax[$filename] = $source->getCode();
		}

		return $converted_syntax;
	}

	protected function make_zip($zip_directory, $zip_filename, array $file_contents)
	{
		if (!$this->filesystem->is_writable($zip_directory))
		{
			throw new http_exception(400, 'TWIGCONVERTER_ERROR_WRITEABLE');
		}

		$zip = new \ZipArchive();
		$error_code = $zip->open($zip_directory . $zip_filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		if (true !== $error_code)
		{
			throw new http_exception(400, 'TWIGCONVERTER_ERROR_ZIP', [$error_code]);
		}

		try
		{
			foreach ($file_contents as $filename => $contents)
			{
				$zip->addFromString($filename, $contents);
			}
		}
		finally
		{
			$zip->close();
		}
	}

	protected function download($filename, $content_type)
	{
		$response = new BinaryFileResponse($filename);
		$response->headers->set('Content-Type', $content_type);

		return $response
			->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($filename))
			->prepare($this->symfony_request)
			->send();
	}
}
