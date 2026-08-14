<?php

class CookieServiceListboxField extends ListboxField
{
    public function saveInto(DataObjectInterface $record)
    {
        if (!$this->multiple) {
            parent::saveInto($record);
            return;
        }

        $fieldname = $this->name;
        $relation = ($fieldname && $record && $record->hasMethod($fieldname)) ? $record->$fieldname() : null;
        if (!($relation instanceof RelationList || $relation instanceof UnsavedRelationList)) {
            parent::saveInto($record);
            return;
        }

        $selectedValues = is_array($this->value) ? $this->value : [];
        $selectedNames = [];
        foreach ($selectedValues as $value) {
            $name = trim((string) $value);
            if ($name !== '') {
                $selectedNames[$name] = $name;
            }
        }

        $idList = [];
        foreach ($selectedNames as $serviceName) {
            $service = CookieService::get()->filter('Name', $serviceName)->first();
            if (!$service) {
                $service = CookieService::create();
                $service->Name = $serviceName;
                $service->write();
            }

            $idList[] = (int) $service->ID;
        }

        $relation->setByIDList($idList);
    }
}
