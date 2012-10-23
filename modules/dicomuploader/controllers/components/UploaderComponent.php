<?php
/*=========================================================================
MIDAS Server
Copyright (c) Kitware SAS. 20 rue de la Villette. All rights reserved.
69328 Lyon, FRANCE.

See Copyright.txt for details.
This software is distributed WITHOUT ANY WARRANTY; without even
the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
PURPOSE.  See the above copyright notices for more information.
=========================================================================*/
include_once BASE_PATH . '/library/KWUtils.php';
/** Uploade dicom files */
class Dicomuploader_UploaderComponent extends AppComponent
{

  /**
   * Verify that DICOM uploader is setup properly
   */
  public function isDICOMUploaderWorking()
  {
    $ret = array();
    $modulesConfig = Zend_Registry::get('configsModules');
    $dcm2xmlCommand = $modulesConfig['dicomuploader']->dcm2xml;
    $storescpCommand = $modulesConfig['dicomuploader']->storescp;
    $kwdicomextractorComponent = MidasLoader::loadComponent('Extractor', 'dicomextractor');
    $ret['dcm2xml'] = $kwdicomextractorComponent->getApplicationStatus($dcm2xmlCommand, 'dcm2xml');
    $ret['storescp'] = $kwdicomextractorComponent->getApplicationStatus($storescpCommand,
                                                   'storescp');
    $receptionDir= $modulesConfig['dicomuploader']->receptiondir;
    if (empty($receptionDir))
      {
      $receptionDir = $this->getDefaultReceptionDir();
      }
    $ret['Uploader Reception Folder Writable'] = array(is_writable($receptionDir));

    return $ret;
  }

  /**
   * Get default reception directory
   */
  public function getDefaultReceptionDir()
  {
    $utilityComponent = MidasLoader::loadComponent('Utility');
    $default_reception_dir = $utilityComponent->getTempDirectory('');
    if(substr($default_reception_dir, -1) == '/')
      {
      $default_reception_dir = substr($default_reception_dir, 0, -1);
      }
    $default_reception_dir .= 'dicomuploader';
    if(!file_exists($default_reception_dir) && !KWUtils::mkDir($default_reception_dir, 0777))
      {
      throw new Zend_Exception("couldn't create dir ".$default_reception_dir);
      }
    $processing_dir = $default_reception_dir . '/processing';
    KWUtils::mkDir($processing_dir, 0777);
    return $default_reception_dir;
  }

} // end class