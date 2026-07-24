<?php

namespace App\Support;

/**
 * Assigns a display colour to each leave category for the leave calendar and
 * its legend.
 *
 * Categories are user-defined and the table carries no colour column, so the
 * colour is derived from the category's position when ordered by id — i.e. the
 * order they were created in. That keeps a category's colour stable for its
 * whole life: renaming it, deactivating a different category, or adding a new
 * one never re-shuffles the colours people have already learned. A new category
 * simply takes the next colour in the palette.
 *
 * The palette wraps once it is exhausted, so a company with more categories
 * than colours gets repeats rather than an error.
 */
class LeaveCategoryColor
{
    /** Distinct hues that stay legible as a 12px dot and as a pale bar fill. */
    private const PALETTE = [
        '#12b76a', // green
        '#f04438', // red
        '#f79009', // amber
        '#06aed4', // cyan
        '#84cc16', // lime
        '#ef6820', // orange
        '#ee46bc', // pink
        '#2e90fa', // blue
        '#667085', // grey
        '#875bf7', // violet
        '#15b79e', // teal
        '#eaaa08', // yellow
    ];

    public const FALLBACK = '#667085';

    /**
     * Build an id => hex colour map for the given categories.
     *
     * @param iterable<int, \App\Models\LeaveCategory> $categories
     * @return array<int, string>
     */
    public static function map(iterable $categories): array
    {
        $ids = [];
        foreach ($categories as $category) {
            $ids[] = (int) $category->id;
        }

        // Order by id here rather than relying on the caller's sort, which is
        // usually alphabetical for display.
        sort($ids);

        $map = [];
        foreach ($ids as $index => $id) {
            $map[$id] = self::PALETTE[$index % count(self::PALETTE)];
        }

        return $map;
    }
}
