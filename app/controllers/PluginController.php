<?php
// app/controllers/PluginController.php

class PluginController extends Controller
{
    public function index(): void
    {
        Auth::requireAdmin();
        $plugins = PluginLoader::getAll();
        $this->view('plugins.index', compact('plugins'));
    }

    public function toggle(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $slug    = $this->sanitize($this->input('slug', ''));
        $enable  = $this->input('action') === 'enable';
        $plugins = PluginLoader::getAll();

        if (!isset($plugins[$slug])) {
            $this->flashError('Plugin not found.');
            $this->redirect('plugins');
            return;
        }

        if ($enable) {
            PluginLoader::enable($slug);
            $this->flashSuccess("Plugin \"{$plugins[$slug]['name']}\" enabled. Reload the page to activate.");
        } else {
            PluginLoader::disable($slug);
            $this->flashSuccess("Plugin \"{$plugins[$slug]['name']}\" disabled.");
        }

        $this->redirect('plugins');
    }
}
