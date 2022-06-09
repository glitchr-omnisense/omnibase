<?php

namespace Base\Config\Traits;

use Base\Config\Menu\SectionWidgetItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;

trait WidgetTrait
{
    public function getSectionWidgetItem(array $widgets = [], $positionOrLabel = null): ?SectionWidgetItem
    {
        $sectionOffsetAndLength = [0, null];
        if(!$positionOrLabel) return $sectionOffsetAndLength;

        $sectionFound   = false;
        $sectionCounter = 0;
        foreach($widgets as $key => $widget) {

            if($widget instanceof SectionWidgetItem) {

                if ($sectionFound) break;

                $sectionFound |= (is_int   ($positionOrLabel) && $positionOrLabel == $sectionCounter);
                $sectionFound |= (is_string($positionOrLabel) && $widget->getAsDto()->getLabel() == $positionOrLabel);

                if ($sectionFound) return $widget;

                $sectionCounter++;
            }
        }

        return null;
    }

    public function getSectionWidgetItemOffsetAndLength(array $widgets = [], $sectionOrPositionOrLabel = null): array
    {
        $sectionOffsetAndLength = [count($widgets), null];
        if(!$sectionOrPositionOrLabel) return $sectionOffsetAndLength;

        $sectionCounter = 0;
        $sectionFound   = false;
        foreach($widgets as $key => $widget) {

            if($widget instanceof SectionWidgetItem) {

                if ($sectionFound) break;

                $sectionFound  = (is_object($sectionOrPositionOrLabel) && $sectionOrPositionOrLabel === $widget);
                $sectionFound |= (is_int   ($sectionOrPositionOrLabel) && $sectionOrPositionOrLabel == $sectionCounter);
                $sectionFound |= (is_string($sectionOrPositionOrLabel) && $widget->getAsDto()->getLabel() == $sectionOrPositionOrLabel);

                if ($sectionFound)
                    $sectionOffsetAndLength = [$key, 0];

                $sectionCounter++;
            }

            if ($sectionFound)
                $sectionOffsetAndLength[1]++;
        }

        return $sectionOffsetAndLength;
    }

    public function extractSectionWidgetItem(array $widgets, int $offset, ?int $length) {

        $previousWidgets   = array_slice($widgets, 0, $offset, true);
        $sectionWidgetItem = ($length !== null ? array_slice($widgets, $offset, 1, true) : []);
        $widgetItems       = ($length !== null ? array_slice($widgets, $offset+1, $length-1, true) : []);
        $nextWidgets       = array_slice($widgets, $offset + $length, NULL, true);

        $sectionWidget = $sectionWidgetItem[0] ?? null;
        if($sectionWidget && !($sectionWidget instanceof SectionWidgetItem))
            throw new \Exception("Expected ".SectionWidgetItem::class." object, but you pass : \"". $sectionWidget."\"");

        foreach ($widgetItems as $widgetItem) {
            if(!($widgetItem instanceof MenuItemInterface))
                throw new \Exception("Expected ".MenuItemInterface::class.", but you pass: \"". $widgetItem."\"");
        }

        return [$previousWidgets, $sectionWidgetItem, $widgetItems, $nextWidgets];
    }

    public function addSectionWidgetItem(array $widgets = [], $sectionOrArray = null, int $position = -1): array
    {
        if(!$sectionOrArray) return $widgets;

        if(!is_array($sectionOrArray))
            $sectionOrArray = [$sectionOrArray];

        foreach ($sectionOrArray as $sectionWidget) {
            if(!($sectionWidget instanceof SectionWidgetItem))
                throw new \Exception("Invalid section widget item provided: ". $sectionWidget);
        }

        [$offset, $_] = $this->getSectionWidgetItemOffsetAndLength($widgets, $position);
        array_splice($widgets, $offset, 0, $sectionOrArray);

        return $widgets;
    }

    public function removeSectionWidgetItem(array $widgets = [], $sectionOrPositionOrLabel = null): array
    {
        if(!$sectionOrPositionOrLabel) return $widgets;

        [$offset, $length] = $this->getSectionWidgetItemOffsetAndLength($widgets, $sectionOrPositionOrLabel);
        [$previousWidgets, $_, $_, $nextWidgets] = $this->extractSectionWidgetItem($widgets, $offset, $length);

        return array_values($previousWidgets + $nextWidgets);
    }

    public function emptySectionWidgetItem(array $widgets, $sectionOrPositionOrLabel): array
    {
        if(!$sectionOrPositionOrLabel) return $widgets;

        [$offset, $length] = $this->getSectionWidgetItemOffsetAndLength($widgets, $sectionOrPositionOrLabel);
        [$previousWidgets, $sectionWidgetItem, $_, $nextWidgets] = $this->extractSectionWidgetItem($widgets, $offset, $length);

        return array_values($previousWidgets + $sectionWidgetItem + $nextWidgets);
    }

    public function addWidgetItem(array $widgets, $sectionOrPositionOrLabel, $itemOrArray, int $position = -1): array
    {
        if(!$sectionOrPositionOrLabel) return $widgets;

        if(!is_array($itemOrArray)) $itemOrArray = [$itemOrArray];
        foreach ($itemOrArray as $item) {
            if($item && !($item instanceof MenuItemInterface))
                throw new \Exception("Invalid section widget item provided: ". $item);
        }

        [$offset, $length] = $this->getSectionWidgetItemOffsetAndLength($widgets, $sectionOrPositionOrLabel);
        [$_, $sectionWidgetItem, $widgetItems, $_] = $this->extractSectionWidgetItem($widgets, $offset, $length);

        if(!$sectionWidgetItem)
            throw new \Exception("Section widget \"". $item."\" not found.");

        if ($position < 0) $position = $length-1;

        array_splice($widgetItems, $position, 0, $itemOrArray);
        array_splice($widgets, $offset+1, $length-1);
        array_splice($widgets, $offset+1, 0, $widgetItems);

        return $widgets;
    }

    public function removeWidgetItem(array $widgets, $sectionOrPositionOrLabel, $widgetItemOrPositionOrLabel): array
    {
        if(!$sectionOrPositionOrLabel) return $widgets;

        [$offset, $length] = $this->getSectionWidgetItemOffsetAndLength($widgets, $sectionOrPositionOrLabel);
        [$previousWidgets, $sectionWidgetItem, $widgetItems, $nextWidgets] = $this->extractSectionWidgetItem($widgets, $offset, $length);

        $widgetItems = array_filter(array_values($widgetItems), function($widget, $widgetItemCounter) use ($widgetItemOrPositionOrLabel) {

            $widgetItemFound  = (is_object($widgetItemOrPositionOrLabel) && $widgetItemOrPositionOrLabel === $widget);
            $widgetItemFound |= (is_int   ($widgetItemOrPositionOrLabel) && $widgetItemOrPositionOrLabel == $widgetItemCounter);
            $widgetItemFound |= (is_string($widgetItemOrPositionOrLabel) && $widget->getAsDto()->getLabel() == $widgetItemOrPositionOrLabel);

            return !$widgetItemFound;

        }, \ARRAY_FILTER_USE_BOTH);

        array_splice($widgets, $offset+1, $length-1);
        array_splice($widgets, $offset+1, 0, $widgetItems);

        return $widgets;
    }
}
