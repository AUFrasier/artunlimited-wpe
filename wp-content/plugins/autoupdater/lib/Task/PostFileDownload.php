<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_PostFileDownload extends AutoUpdater_Task_Base
{
    /**
     * @throws AutoUpdater_Exception_Response
     * @throws Exception
     *
     * @return array
     */
    public function doTask()
    {
        $type = $this->input('type');
        $slug = $this->input('slug');

        if (!$this->input('file_url') && ($type == 'plugin' || $type == 'theme') && $slug) {
            $updates = get_site_transient($type == 'plugin' ? 'update_plugins' : 'update_themes');
            if (!empty($updates->response[$slug])) {
                $update = (array) $updates->response[$slug];
                if (!empty($update['package'])) {
                    $this->setInput('file_url', $update['package']);
                }
            }
        }

        $url = $this->input('file_url');
        if (!$url) {
            throw new AutoUpdater_Exception_Response('Nothing to download', 400);
        }

        $filemanager = AutoUpdater_Filemanager::getInstance();
        // Force the temporary directory within the site path to have access to a new file on all containers
        if (!$filemanager->setTempDirWitihnSite()) {
            throw new AutoUpdater_Exception_Response('Failed to set temporary directory within site path', 500);
        }
        $path = $filemanager->download($url);

        return array(
            'success' => $path ? true : false,
            'return' => array(
                'file_path' => $filemanager->trimPath($path),
            ),
        );
    }
}
