<?php

namespace Framework\View\Engine;
use Framework\view\View;

class TemplateEngine implements Engine {
	protected $layouts = [];

	public function render(string $path, array $data = []): string {
		$hash = md5($path);
		$folder = dirname($path);
		$cached = "{$folder}/cache/{$hash}.php";
		if (!file_exists($hash) || fileatime($path) > filemtime($hash)) {
			$content = $this->compile(file_get_contents($path));
			file_put_contents($cached, $content);
		}
		extract($data);
		ob_start();
		include($cached);
		$contents = ob_get_contents();
		ob_end_clean();
		if ($layout = $this->layouts[$cached] ?? null) {
			return view($layout, $data,['contents' => $contents]);
		}
		return $contents;
	}
	protected function compile(string $template): string {
		$template = preg_replace_callback('#@extends\(([^)]+)\)@#', function($matches){
			return '<?php include_once $this->extends('.$matches[1].'); ?>';
		}, $template);
		$template = preg_replace_callback('#@if\((.*?)@#', function($matches) {
			// dd($matches);
			return '<?php if('.$matches[1].' : ?>';
		}, $template);
		$template = preg_replace_callback('#@foreach\((.*?)@#', function($matches) {
			return '<?php foreach('.$matches[1].' : ?>';
		}, $template);
		$template = preg_replace_callback('#@endforeach@#', function($matches) {
			return '<?php endforeach; ?>';
		}, $template);
		$template = preg_replace_callback('#@endif@#', function($matches) {
			return '<?php endif; ?>';
		}, $template);
		$template = preg_replace_callback('#\{\{([^}]+)}\}#', function($matches) {
			return '<?php print $this->escape($'.$matches[1].'); ?>';
		},$template);
		$template = preg_replace_callback('#\{([^}]+)}#', function($matches) {
			return '<?php print $this->escape('.$matches[1].'); ?>';
		},$template);
		$template = preg_replace_callback('#@csrf@#', function($matches) {
			$csrf = csrf();
			return "<input type='hidden' name='csrf' value='{$csrf}'>";
		},$template);
		return $template;
	}

	protected function extends(string $template){
		$layout = BASE_PATH . '/templates/layout/' . $template . '.template.php';
		return $layout;
	}

	protected function escape(string $content): string {
		return htmlspecialchars($content);
	}
}
