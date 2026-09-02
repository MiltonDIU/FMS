<?php

namespace App\Support;

/**
 * The teacher columns the ERP profile sync is allowed to write.
 *
 * A whitelist, not a convenience list. The ERP mapping resolves close to forty
 * columns — names, department, designation, employment status, and the webpage
 * slug every public profile URL is built from. A sync meant to top up personal
 * and contact details has no business rewriting any of those, and one wrong
 * mapping row would otherwise be enough to move a teacher to another department
 * or break their public address. Anything not named here is dropped before a
 * value is written.
 *
 * Two entries are not named the way the request was worded, because the columns
 * are not:
 *  - "Work Phone" is the `phone` column. The ERP mapping already reads the
 *    vendor's `workPhone` field into it; there is no `work_phone` column.
 *  - "Nationality" is `country_id`. The mapping resolves the vendor's
 *    nationality name to a country row through LookupResolver, so what lands
 *    here is an id, not the text.
 *
 * Gender, Nationality, Religion and Blood Group all arrive the same way: the
 * ERP sends a name, the mapping row carries is_relation, and LookupResolver
 * turns it into the id of the matching lookup row.
 *
 * Biography is the one field here somebody may have written by hand — it is the
 * paragraph on the public profile, and a teacher can edit their own. It is
 * listed because the ERP has one and most profiles have none, but it is also
 * the field to think twice about before choosing "Overwrite": on that setting a
 * run replaces edited text with whatever the ERP holds, and the previous
 * wording is not kept anywhere.
 */
class ErpProfileFields
{
    /**
     * Column => the label an operator sees when choosing what to fill.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'joining_date' => 'Joining Date',
            'date_of_birth' => 'Date of Birth',
            'phone' => 'Work Phone',
            'personal_phone' => 'Personal Phone',
            'gender_id' => 'Gender',
            'country_id' => 'Nationality',
            'religion_id' => 'Religion',
            'blood_group_id' => 'Blood Group',
            'work_location' => 'Work Location',
            'present_address' => 'Present Address',
            'permanent_address' => 'Permanent Address',
            'bio' => 'Biography',
        ];
    }

    /**
     * Fields the ERP is not currently sending back, checked against the live
     * endpoint rather than assumed.
     *
     * The mapping rows for these exist and are correct; the vendor's profile
     * response simply carries no key for them — searching a full response for
     * anything matching address, bio, about or summary finds nothing. Left on
     * the list so they start working the day the ERP begins sending them, and
     * named here so the form can say so instead of offering a checkbox that
     * quietly fills nothing.
     *
     * Delete an entry once the ERP supplies it.
     *
     * @return array<int, string>
     */
    public static function notSuppliedByErp(): array
    {
        return ['present_address', 'permanent_address', 'bio'];
    }

    /**
     * Per-field notes for the checkbox list.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return array_fill_keys(
            static::notSuppliedByErp(),
            'The ERP does not return this yet — selecting it fills nothing.',
        );
    }

    /** @return array<int, string> */
    public static function columns(): array
    {
        return array_keys(static::all());
    }

    /**
     * The fields worth ticking by default: the ones the ERP actually answers with.
     *
     * @return array<int, string>
     */
    public static function defaultSelection(): array
    {
        return array_values(array_diff(static::columns(), static::notSuppliedByErp()));
    }

    /**
     * The given columns, reduced to the ones actually allowed.
     *
     * Everything that reaches the job goes through here: a field list arrives
     * from a form, and a form can be edited before it is submitted.
     *
     * @param  array<int, mixed>  $columns
     * @return array<int, string>
     */
    public static function only(array $columns): array
    {
        $columns = array_filter($columns, 'is_string');

        return array_values(array_intersect($columns, static::columns()));
    }

    /**
     * Labels for a set of columns, for saying in a notification what was filled.
     *
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    public static function labels(array $columns): array
    {
        $all = static::all();

        return array_values(array_map(fn (string $column): string => $all[$column] ?? $column, $columns));
    }
}
