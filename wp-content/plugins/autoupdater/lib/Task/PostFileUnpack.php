<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_PostFileUnpack extends AutoUpdater_Task_Base
{
    /**
     * @throws AutoUpdater_Exception_Response
     * @throws Exception
     *
     * @return array
     */
    public function doTask()
    {
        $file_path = $this->input('file_path');
        if (empty($file_path)) {
            throw new AutoUpdater_Exception_Response('Nothing to unpack', 400);
        }

        $filemanager = AutoUpdater_Filemanager::getInstance();
        $destination = $this->input('destination');

        if (!$filemanager->is_file($file_path)) {
            $file_path = AUTOUPDATER_SITE_PATH . $file_path;
            if (!$filemanager->is_file($file_path)) {
                throw new AutoUpdater_Exception_Response('Archive not found', 404);
            }
        }
        $file_path = realpath($file_path);

        $result = array(
            'success' => false,
        );

        try {
            $destination = $filemanager->unpack($file_path, $destination);
            if ($destination) {
                $result['success'] = true;
                $result['return'] = array(
                    'path' => $filemanager->trimPath($destination),
                );

                $filemanager->clearPhpCache();
            }
        } catch (Exception $e) {
            // Throw the exception after deleting the downloaded file
            $exception = $e;
        }

        try {
            // Delete the downloaded file
            $filemanager->delete($file_path);
        } catch (Exception $e) {
        }

        if (isset($exception)) {
            // Throw the exception with the unpack error
            throw $exception;
        }

        return $result;
    }
}
