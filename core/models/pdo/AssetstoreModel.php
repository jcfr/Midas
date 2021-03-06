<?php
/*=========================================================================
 Midas Server
 Copyright Kitware SAS, 26 rue Louis Guérin, 69100 Villeurbanne, France.
 All rights reserved.
 For more information visit http://www.kitware.com/.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

require_once BASE_PATH.'/core/models/base/AssetstoreModelBase.php';

/** Pdo Model. */
class AssetstoreModel extends AssetstoreModelBase
{
    /**
     * Get all.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->database->getAll('Assetstore');
    }

    /**
     * Move all bitstreams from one asset store to another.
     *
     * @param AssetstoreDao $srcAssetstore The source asset store
     * @param AssetstoreDao $dstAssetstore The destination asset store
     * @param null|ProgressDao $progressDao Progress dao for asynchronous updating
     * @throws Zend_Exception
     */
    public function moveBitstreams($srcAssetstore, $dstAssetstore, $progressDao = null)
    {
        $current = 0;

        /** @var ProgressModel $progressModel */
        $progressModel = MidasLoader::loadModel('Progress');

        /** @var BitstreamModel $bitstreamModel */
        $bitstreamModel = MidasLoader::loadModel('Bitstream');

        $sql = $this->database->select()->setIntegrityCheck(false)->from('bitstream')->where(
            'assetstore_id = ?',
            $srcAssetstore->getKey()
        );
        $rows = $this->database->fetchAll($sql);

        $srcPath = $srcAssetstore->getPath();
        $dstPath = $dstAssetstore->getPath();

        foreach ($rows as $row) {
            $bitstream = $this->initDao('Bitstream', $row);
            if ($progressDao) {
                ++$current;
                $message = $current.' / '.$progressDao->getMaximum().': Moving '.$bitstream->getName(
                    ).' ('.UtilityComponent::formatSize($bitstream->getSizebytes()).')';
                $progressModel->updateProgress($progressDao, $current, $message);
            }

            // Move the file on disk to its new location
            $dir1 = substr($bitstream->getChecksum(), 0, 2);
            $dir2 = substr($bitstream->getChecksum(), 2, 2);
            if (!is_dir($dstPath.'/'.$dir1)) {
                if (!mkdir($dstPath.'/'.$dir1)) {
                    throw new Zend_Exception('Failed to mkdir '.$dstPath.'/'.$dir1);
                }
            }
            if (!is_dir($dstPath.'/'.$dir1.'/'.$dir2)) {
                if (!mkdir($dstPath.'/'.$dir1.'/'.$dir2)) {
                    throw new Zend_Exception('Failed to mkdir '.$dstPath.'/'.$dir1.'/'.$dir2);
                }
            }

            if (is_file($dstPath.'/'.$bitstream->getPath())) {
                if (is_file($srcPath.'/'.$bitstream->getPath())) {
                    unlink($srcPath.'/'.$bitstream->getPath());
                }
            } else {
                if (!rename($srcPath.'/'.$bitstream->getPath(), $dstPath.'/'.$bitstream->getPath())
                ) {
                    throw new Zend_Exception('Error moving '.$bitstream->getPath());
                }
            }

            // Update the asset store id on the bitstream record once it has been moved
            $bitstream->setAssetstoreId($dstAssetstore->getKey());
            $bitstreamModel->save($bitstream);
        }
    }
}
