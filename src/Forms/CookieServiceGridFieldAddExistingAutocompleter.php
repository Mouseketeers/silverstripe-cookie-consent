<?php

class CookieServiceGridFieldAddExistingAutocompleter extends GridFieldAddExistingAutocompleter
{
    protected $serviceOptions = array();

    public function __construct($targetFragment = 'before', $serviceOptions = array())
    {
        parent::__construct($targetFragment);
        $this->serviceOptions = $serviceOptions;
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
            return Convert::array2json(array());
        }

        $json = array();
        $serviceOptions = $this->getServiceOptions();
        if (!is_array($serviceOptions)) {
            return Convert::array2json($json);
        }

        foreach ($serviceOptions as $value => $label) {
            if (stripos((string) $label, $searchTerm) === false && stripos((string) $value, $searchTerm) === false) {
                continue;
            }

            $service = CookieService::get()->filter('Name', $value)->first();
            if ($service) {
                $existingRelationIds = $gridField->getList()->column('ID');
                if (in_array($service->ID, $existingRelationIds, true)) {
                    continue;
                }
            }

            $json[$value] = $value;
        }

        return Convert::array2json($json);
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
            $object = DataObject::get_by_id($dataClass, (int) $objectID);
        } else {
            $object = CookieService::get()->filter('Name', $objectID)->first();
            if (!$object) {
                $object = CookieService::create();
                $object->Name = $objectID;
                $object->write();
            }
        }

        if ($object && $object instanceof CookieService) {
            $existingRelationIds = array();
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