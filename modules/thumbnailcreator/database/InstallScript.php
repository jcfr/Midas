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

require_once BASE_PATH.'/modules/thumbnailcreator/constant/module.php';

/** Install the thumbnailcreator module. */
class Thumbnailcreator_InstallScript extends MIDASModuleInstallScript
{
    /** @var string */
    public $moduleName = 'thumbnailcreator';

    /** Post database install. */
    public function postInstall()
    {
        /** @var SettingModel $settingModel */
        $settingModel = MidasLoader::loadModel('Setting');
        $settingModel->setConfig(MIDAS_THUMBNAILCREATOR_PROVIDER_KEY, MIDAS_THUMBNAILCREATOR_PROVIDER_DEFAULT_VALUE, $this->moduleName);
        $settingModel->setConfig(MIDAS_THUMBNAILCREATOR_FORMAT_KEY, MIDAS_THUMBNAILCREATOR_FORMAT_DEFAULT_VALUE, $this->moduleName);
        $settingModel->setConfig(MIDAS_THUMBNAILCREATOR_IMAGE_MAGICK_KEY, MIDAS_THUMBNAILCREATOR_IMAGE_MAGICK_DEFAULT_VALUE, $this->moduleName);
        $settingModel->setConfig(MIDAS_THUMBNAILCREATOR_USE_THUMBNAILER_KEY, MIDAS_THUMBNAILCREATOR_USE_THUMBNAILER_DEFAULT_VALUE, $this->moduleName);
        $settingModel->setConfig(MIDAS_THUMBNAILCREATOR_THUMBNAILER_KEY, MIDAS_THUMBNAILCREATOR_THUMBNAILER_DEFAULT_VALUE, $this->moduleName);
    }
}
