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

$itemModel = MidasLoader::loadModel('Item');
// parse and check arguments
foreach ($args as $key => $val) {
    switch ($key) {
        case 'from':
            if (!isset($from)) {
                $from = $val;
            } else {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;

        case 'until':
            if (!isset($until)) {
                $until = $val;
            } else {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;

        case 'set':
            if (!isset($set)) {
                $set = $val;
            } else {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;

        case 'metadataPrefix':
            if (!isset($metadataPrefix)) {
                if (is_array($METADATAFORMATS[$val]) && isset($METADATAFORMATS[$val]['myhandler'])) {
                    $metadataPrefix = $val;
                    $inc_record = $METADATAFORMATS[$val]['myhandler'];
                } else {
                    $errors .= oai_error('cannotDisseminateFormat', $key, $val);
                }
            } else {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;

        case 'resumptionToken':
            if (!isset($resumptionToken)) {
                $resumptionToken = $val;
            } else {
                $errors .= oai_error('badArgument', $key, $val);
            }
            break;

        default:
            $errors .= oai_error('badArgument', $key, $val);
    }
}

// Resume previous session?
if (isset($args['resumptionToken'])) {
    if (count($args) > 1) {
        // overwrite all other errors
        $errors = oai_error('exclusiveArgument');
    } else {
        if (is_file("tokens/id-$resumptionToken")) {
            $fp = fopen("tokens/id-$resumptionToken", 'r');
            $filetext = fgets($fp, 255);
            $textparts = explode('#', $filetext);
            $deliveredrecords = (int) $textparts[0];
            $extquery = $textparts[1];
            $metadataPrefix = $textparts[2];
            fclose($fp);
            unlink("tokens/id-$resumptionToken");
        } else {
            $errors .= oai_error('badResumptionToken', '', $resumptionToken);
        }
    }
} // no, new session
else {
    $deliveredrecords = 0;
    $extquery = '';

    if (!isset($args['metadataPrefix'])) {
        $errors .= oai_error('missingArgument', 'metadataPrefix');
    }

    if (isset($args['from'])) {
        if (!checkDateFormat($from)) {
            $errors .= oai_error('badGranularity', 'from', $from);
        }
        $extquery .= " and last_modified >= '$from'";
    }

    if (isset($args['until'])) {
        if (!checkDateFormat($until)) {
            $errors .= oai_error('badGranularity', 'until', $until);
        }
        $extquery .= " and last_modified <= '$until'";
    }

    if (isset($args['set'])) {
        if (is_array($SETS)) {
            $extquery .= " and handle LIKE '%$set%'";
        } else {
            $errors .= oai_error('noSetHierarchy');
            oai_exit();
        }
    }
}

if (empty($errors)) {
    // Hate that... Imagine if there are 2 millions items...
    $items = $itemModel->getAll();
    // TODO change this call to get all items and filter the items
    // to a new method in ItemModel getAllPublic
    $publicItems = array();
    foreach ($items as $item) {
        if ($itemModel->policyCheck($item, null, MIDAS_POLICY_READ)) {
            $publicItems[] = $item;
        }
    }
    $items = $publicItems;

    if (empty($items)) {
        $errors .= oai_error('noRecordsMatch');
    }
}

// break and clean up on error
if ($errors != '') {
    oai_exit();
}

$output .= " <ListIdentifiers>\n";

// Will we need a ResumptionToken?
if (count($items) - $deliveredrecords > $MAXIDS) {
    $token = get_token();
    $tokensDir = UtilityComponent::getTempDirectory().'/tokens';
    if (!is_dir($tokensDir)) {
        mkdir($tokensDir);
    }
    $tokensPath = $tokensDir.'/id-'.$token;
    $fp = fopen($tokensPath, 'w');
    $thendeliveredrecords = (int) $deliveredrecords + $MAXIDS;
    fwrite($fp, "$thendeliveredrecords#");
    fwrite($fp, "$extquery#");
    fclose($fp);
    $num_rows = count($items);
    $restoken = '  <resumptionToken expirationDate="'.$expirationdatetime.'"
     completeListSize="'.$num_rows.'"
     cursor="'.$deliveredrecords.'">'.$token."</resumptionToken>\n";
} // Last delivery, return empty ResumptionToken
elseif (isset($set_resumptionToken)) {
    $num_rows = count($items);
    $restoken = '  <resumptionToken completeListSize="'.$num_rows.'"
     cursor="'.$deliveredrecords.'"></resumptionToken>'."\n";
}

$maxrec = min(count($items) - $deliveredrecords, $MAXIDS);
$countrec = 0;
while ($countrec++ < $maxrec) {
    $element = $items[$countrec - 1];
    if (!$element instanceof ItemDao || !$itemModel->policyCheck($element, null, MIDAS_POLICY_READ)) {
        continue;
    }
    $identifier = $oaiprefix.$element->getUuid();
    $datestamp = formatDatestamp($element->getDateUpdate());

    $output .= '  <header';

    $output .= '>'."\n";

    // use xmlrecord since we use stuff from database
    $output .= xmlrecord($identifier, 'identifier', '', 3);
    $output .= xmlformat($datestamp, 'datestamp', '', 3);

    $folders = $element->getFolders();

    if (empty($folders)) {
        $errors .= oai_error('resourceIdDoesNotExist', '', $record[1]);
    }

    foreach ($folders as $folder) {
        $setspec = $setspecprefix.str_replace('/', '_', $folder->getUuid());
        $output .= xmlrecord($setspec, 'setSpec', '', 3);
    }
    $output .= '   </header>'."\n";
}

// ResumptionToken
if (isset($restoken)) {
    $output .= $restoken;
}

$output .= " </ListIdentifiers>\n";
