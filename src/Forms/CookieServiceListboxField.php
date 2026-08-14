<?php

class CookieServiceListboxField extends ListboxField
{
    protected $relationName = 'CookieServices';

    public function setRelationName($relationName)
    {
        $this->relationName = trim((string) $relationName);
        return $this;
    }

    public function getRelationName()
    {
        return $this->relationName;
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

        $selectedValues = is_array($this->value) ? $this->value : [];
        $selectedNames = [];
        foreach ($selectedValues as $value) {
            $name = trim((string) $value);
            if ($name !== '') {
                $selectedNames[$name] = $name;
            }
        }

        // Remove deselected services for this SiteConfig entirely.
        $existingServices = CookieService::get()->filter('SiteConfigID', $siteConfigId);
        foreach ($existingServices as $existingService) {
            if (!isset($selectedNames[$existingService->Name])) {
                $existingService->delete();
            }
        }

        $idList = [];
        foreach ($selectedNames as $serviceName) {
            $service = CookieService::get()
                ->filter('Name', $serviceName)
                ->filter('SiteConfigID', $siteConfigId)
                ->first();

            if (!$service) {
                $service = CookieService::create();
                $service->Name = $serviceName;
                $service->SiteConfigID = $siteConfigId;
                $service->write();
            }

            $idList[] = (int) $service->ID;
        }

        // Keep relation consistent for the selected IDs.
        $relation->setByIDList($idList);
    }
}
