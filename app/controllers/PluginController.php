<?php
// app/controllers/PluginController.php

class PluginController extends Controller
{
    /**
     * Purpose : Render the plugin management page (admin only).
     */
    public function index(): void
    {
        Auth::requireAdmin();
        $plugins = PluginLoader::getAll();
        $this->view('plugins.index', compact('plugins'));
    }

    /**
     * Purpose : Enable or disable a plugin (toggle).
     * Inputs  : POST slug, action ('enable'|'disable')
     */
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

    /**
     * Purpose : Install a plugin from an uploaded .zip file.
     * Inputs  : POST (multipart) — file field 'plugin_zip'
     */
    public function install(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $upload = $_FILES['plugin_zip'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
            $this->flashError('Upload failed. Please select a valid .zip file.');
            $this->redirect('plugins');
            return;
        }

        $ext  = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($upload['tmp_name']);

        if ($ext !== 'zip' || !in_array($mime, ['application/zip', 'application/x-zip-compressed'], true)) {
            $this->flashError('Only .zip files are accepted for plugin installation.');
            $this->redirect('plugins');
            return;
        }

        if ($upload['size'] > 10 * 1024 * 1024) {
            $this->flashError('Plugin zip exceeds the 10 MB size limit.');
            $this->redirect('plugins');
            return;
        }

        $result = PluginInstaller::installFromZip($upload['tmp_name']);

        if ($result['success']) {
            $this->flashSuccess($result['message']);
        } else {
            $this->flashError('Installation failed: ' . $result['message']);
        }

        $this->redirect('plugins');
    }

    /**
     * Purpose : Uninstall a 3rd-party plugin (backs up files, removes from DB).
     * Inputs  : POST slug
     */
    public function uninstall(): void
    {
        Auth::requireAdmin();
        Auth::verifyCsrf();

        $slug = $this->sanitize($this->input('slug', ''));

        if (empty($slug)) {
            $this->flashError('No plugin specified.');
            $this->redirect('plugins');
            return;
        }

        $result = PluginInstaller::uninstall($slug);

        if ($result['success']) {
            $this->flashSuccess($result['message']);
        } else {
            $this->flashError('Uninstall failed: ' . $result['message']);
        }

        $this->redirect('plugins');
    }
}
