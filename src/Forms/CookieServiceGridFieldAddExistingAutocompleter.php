<?php

namespace Mouseketeers\CookieConsent\Forms;

use SilverStripe\Model\List\SS_List;
use Mouseketeers\CookieConsent\Models\CookieService;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\ORM\DataObject;

class CookieServiceGridFieldAddExistingAutocompleter extends GridFieldAddExistingAutocompleter
{
    public function __construct($targetFragment = 'before', protected $serviceOptions = [])
    {
        parent::__construct($targetFragment);
    }

    public function setServiceOptions($serviceOptions)
    {
        $this->serviceOptions = $serviceOptions;
        return $this;
    }

    public function getServiceOptions()
    {
        return $this->serviceOptions;
    }

    public function doSearch($gridField, $request)
    {
        $searchTerm = trim((string) $request->getVar('gridfield_relationsearch'));
        if ($searchTerm === '') {
            return json_encode([]);
        }

        $json = [];
        $serviceOptions = $this->getServiceOptions();
        if (!is_array($serviceOptions)) {
            return json_encode($json);
        }

        foreach ($serviceOptions as $value => $label) {
            if (stripos((string) $label, $searchTerm) === false && stripos((string) $value, $searchTerm) === false) {
                continue;
            }

            $service = CookieService::get()->filter(['Name' => $value])->first();
            if ($service) {
                $existingRelationIds = $gridField->getList()->column('ID');
                if (in_array($service->ID, $existingRelationIds, true)) {
                    continue;
                }
            }

            $json[$value] = $value;
        }

        return json_encode($json);
    }

    public function getManipulatedData(GridField $gridField, SS_List $dataList)
    {
        $objectID = $gridField->State->GridFieldAddRelation(null);
        if (empty($objectID)) {
            return $dataList;
        }

        $object = null;
        if (is_numeric($objectID)) {
            $dataClass = $gridField->getModelClass();
            $object = DataObject::get($dataClass)->setUseCache(true)->byID((int) $objectID);
        } else {
            $object = CookieService::get()->filter(['Name' => $objectID])->first();
            if (!$object) {
                $object = CookieService::create();
                $object->Name = $objectID;
                $object->write();
            }
        }

        if ($object && $object instanceof CookieService) {
            $existingRelationIds = [];
            foreach ($dataList as $existingObject) {
                $existingRelationIds[] = (int) $existingObject->ID;
            }

            if (!in_array((int) $object->ID, $existingRelationIds, true)) {
                $dataList->add($object);
            }
        }

        return $dataList;
    }
}
