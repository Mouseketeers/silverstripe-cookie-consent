<?php

class CookieServiceListboxField extends ListboxField
{
    protected $relationName = 'CookieServices';

    protected $dataObjectClass = 'CookieService';

    public function setRelationName($relationName)
    {
        $this->relationName = trim((string) $relationName);
        return $this;
    }

    public function getRelationName()
    {
        return $this->relationName;
    }

    public function setDataObjectClass($dataObjectClass)
    {
        $this->dataObjectClass = trim((string) $dataObjectClass);
        return $this;
    }

    public function getDataObjectClass()
    {
        return $this->dataObjectClass;
    }

    public function saveInto(DataObjectInterface $record)
    {
        if (!$this->multiple) {
            parent::saveInto($record);
            return;
        }

        $fieldname = $this->getRelationName();
        $relation = ($fieldname && $record && $record->hasMethod($fieldname)) ? $record->$fieldname() : null;
        if (!($relation instanceof RelationList || $relation instanceof UnsavedRelationList)) {
            parent::saveInto($record);
            return;
        }

        if (empty($record->ID)) {
            parent::saveInto($record);
            return;
        }

        $siteConfigId = (int) $record->ID;
        $dataObjectClass = $this->getDataObjectClass();

        $selectedValues = is_array($this->value) ? $this->value : [];
        $selectedNames = [];
        foreach ($selectedValues as $value) {
            $name = trim((string) $value);
            if ($name !== '') {
                $selectedNames[$name] = $name;
            }
        }

        // Remove deselected items for this SiteConfig entirely.
        $existingItems = $dataObjectClass::get()->filter('SiteConfigID', $siteConfigId);
        foreach ($existingItems as $existingItem) {
            if (!isset($selectedNames[$existingItem->Name])) {
                $existingItem->delete();
            }
        }

        $idList = [];
        foreach ($selectedNames as $itemName) {
            $item = $dataObjectClass::get()
                ->filter('Name', $itemName)
                ->filter('SiteConfigID', $siteConfigId)
                ->first();

            if (!$item) {
                $item = $dataObjectClass::create();
                $item->Name = $itemName;
                $item->SiteConfigID = $siteConfigId;
                $item->write();
            }

            $idList[] = (int) $item->ID;
        }

        // Keep relation consistent for the selected IDs.
        $relation->setByIDList($idList);
    }
}
