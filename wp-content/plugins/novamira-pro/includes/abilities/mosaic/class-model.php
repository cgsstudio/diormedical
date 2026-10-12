<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Mosaic class model: Classes, sub classes, Variants and variant sub classes.
 *
 * Everything below was verified against the Mosaic 1.0.8 source (paths are
 * relative to the plugin root) and exercised on a live 1.0.8 install. Mosaic
 * 1.0.8 renamed its whole class vocabulary; this integration targets that
 * model only. Installs that still carry the pre-1.0.8 storage are detected by
 * table / class existence ({@see mo_class_storage()}) and every class- or
 * variant-dependent path returns `mo_legacy_class_storage` there.
 *
 * STORAGE (Mosaic/Database/Schemas/Schema-1.0.8.php)
 *  - `<prefix>mosaic_universal_classes`     Classes (root). Were `utility_classes`.
 *  - `<prefix>mosaic_universal_sub_classes` sub classes of a Class, nested to any
 *    depth (`parentType` = `universalClass` | `universalSubClass`,
 *    UniversalSubClassesMResourceManager::getParentMResource()).
 *  - `<prefix>mosaic_variants`              per-theme style rows for the built-in
 *    Variants. Were `element_classes`. A row's ID IS the catalog UUID
 *    (VariantRevisionRecord::getParentID() returns $this->ID) and a row only
 *    exists once the theme styled that Variant or hung a sub class under it —
 *    ElementMResourceAbstract::getVariantMResource() (:184-216) builds an
 *    in-memory one otherwise.
 *  - `<prefix>mosaic_variant_sub_classes`   variant sub classes, nested to any depth
 *    (`parentType` = `variant` | `variantSubClass`,
 *    VariantSubClassesMResourceManager::getParentMResource() :84-96).
 *  The three class tables carry `emittedName` + UNIQUE(themeID, emittedName).
 *  Only Mosaic's manager saves maintain that column
 *  (UniqueNameMResourceManagerAbstract::pushToDB() :245-262 — resolve name
 *  collisions, write rows, project emitted names), so every class write here
 *  goes through the manager API; a raw UPDATE would leave a stale claim that a
 *  later editor save collides with.
 *
 * RECORD FIELDS (UniversalClassMResourceData.php, UniversalSubClassMResourceData.php,
 * VariantSubClassMResourceData.php)
 *  - `name`: the canonical class-name SEGMENT, stored sanitized by
 *    ValidatorClassNameSegment (input that sanitizes to '' is rejected and the
 *    previous value survives). There is no `cssClass` / `isExcluded` any more.
 *  - `label`: free text, kept by Mosaic only when the name did not slug cleanly.
 *  - `legacyClassName`: the pre-1.0.8 flat class name of a migrated record; it
 *    is rendered as an extra class and selector (LegacyClassNameHelper) but is
 *    never part of the UNIQUE index.
 *  - Classes only: `favoredVariants` (+ `favoredVariantsFilter`). Editor UX
 *    only — the Class picker suggests the class when the selected element's
 *    Variant is in the list; it has no effect on rendering. Accepted values are
 *    the catalog entries whose type is not `tag` and that are editable
 *    (VariantCatalogMResourceManager::getFavorableVariantMResources() :81-85).
 *
 * NAME GRAMMAR (Mosaic/Helpers/ClassNameHelper.php)
 *  - sanitizeSegment() :278-296: transliterate, lowercase, runs of anything
 *    outside [a-z0-9_-] become '-', trim '-'/'_', cap at 60 chars; a ROOT also
 *    strips the reserved `m-` prefix and must start with a-z.
 *  - isValidSegment() :344-373: ^[a-z0-9_-]+$, no `--` (the hierarchy
 *    separator), no leading/trailing '-' or '_'.
 *  - Emitted names: a Class emits its sanitized name; a sub class emits the
 *    compound `<root>--<segment>--<segment>` (deriveFallbackFullName() :421-429,
 *    UniversalSubClassMResource::deriveEmittedName()); a variant sub class
 *    emits `<variant className>--<segment>...`, e.g. `m-button--primary`
 *    (VariantSubClassMResource::deriveEmittedName()).
 *  - Collisions among published siblings (theme-wide for Classes, per parent
 *    for sub classes) are silently deduped `name` → `name-2` → `name-3`
 *    (resolveUniqueSegment() :439, UniversalClassesMResourceManager::
 *    resolveNameCollisions() :147-199). So what we store is read back, never
 *    echoed from the input.
 *
 * ELEMENT WIRE SHAPES (Mosaic/Data/StyleData/ElementStyleDataSub.php)
 *  - `data.style.variant`: ONE string, `<variantID>` or
 *    `<variantID>/<variantSubClassID>` (validator :39-64 accepts 1 or 2
 *    UUIDs). Only the leaf sub class is stored; Mosaic walks its parent chain.
 *    Writes REPLACE it. The Variant must be one the element type supports:
 *    ElementTypeFactoryAbstract::getVariantMResourceUUIDByMElement() :45-58
 *    silently falls back to the type's default for anything else, e.g.
 *    ButtonElementTypeFactory::init() :19-21 supports Button / Inherited text
 *    (catalog name "Link") / Badge, default Button (:32-33).
 *  - `data.style.universalClasses`: an object keyed by ROOT Class UUID whose
 *    value is the attached UUID — the root itself, or one sub class of that
 *    root at any depth (validator :85-110 drops non-UUID pairs; an empty object
 *    becomes null). One entry per root, so writes MERGE BY KEY: attaching a
 *    different sub class of the same root overwrites that root's entry. The
 *    editor bundle does exactly this (`t[classID] = attachedID;
 *    data.store("universalClasses", t)` / `delete t[classID]`).
 *  - `data.style.defaultStyleSelector`: `{type, uuid?}` — which selector the
 *    editor's style panel targets. Not written here.
 *  - `data.cssClasses`: `[{className, uuid}]` raw class strings for the
 *    element's class attribute — classes from outside Mosaic (a theme or plugin
 *    stylesheet). Mosaic never reads the `uuid`: it is only a per-record id
 *    (MElementCSSClass::serveRenderPipeline() :47-51 evaluates `className`
 *    alone; Upgrade-0.0.3 filled it with random UUIDs), and `className` must
 *    match /^[a-zA-Z_-][a-zA-Z0-9_-]*$/ (ValidatorClassName).
 *
 * WHY A CLASS IN `data.cssClasses` RENDERS WITHOUT CSS
 *  UniversalClassMResource::addCSS() :268-285 emits a Class's rules only when
 *  getIsInUseForFrontend() :47 is true, and the only thing that sets that flag
 *  is the element pre-render pipeline reading `style.universalClasses`
 *  (Builder/ElementMResourceStyle.php :99-136; the variant chain :62/:83). A
 *  Mosaic Class name written into `data.cssClasses` therefore lands in the
 *  markup with no stylesheet rule behind it. (Before 1.0.8 a class with a
 *  non-empty `cssClass` counted as always in use, which is why writing
 *  `data.cssClasses` appeared to work.) The 1.0.8 upgrade does NOT touch
 *  `data.cssClasses` (0 occurrences in Upgrade-1.0.8.php / Helper-1.0.8.php),
 *  so pages built that way keep the class name and lose the CSS.
 *
 * DELETES
 *  Mosaic's own delete is MResourceAbstract::delete() (:221-223, flips
 *  `currentStatus`) followed by pushToDB(), which REMOVES the row. The editor
 *  commit then notifies the class detacher (UniversalClassMResource::doCommit()
 *  :333-350) and MResourcesDetacherManager::onAfterCommit() rewrites every
 *  element that referenced it theme-wide
 *  (UniversalClassUniversalSubClassDetacher::transformUniversalClassData():
 *  a deleted root's entry is removed, a deleted sub class is re-pointed at its
 *  nearest surviving ancestor). {@see mo_facade_delete_class_branch()} drives
 *  that same sequence.
 */

/**
 * Tables that make up the class model this integration targets.
 */
const MO_CLASS_MODEL_TABLES = ['universal_classes', 'universal_sub_classes', 'variants', 'variant_sub_classes'];

/**
 * Mosaic classes whose presence proves the running code speaks the class model.
 */
const MO_CLASS_MODEL_CLASSES = [
    'Mosaic\\EditorInstance\\MResources\\UniversalClasses\\UniversalClassesMResourceManager',
    'Mosaic\\EditorInstance\\MResources\\UniversalSubClass\\UniversalSubClassesMResourceManager',
    'Mosaic\\EditorInstance\\MResources\\VariantSubClasses\\VariantSubClassesMResourceManager',
    'Mosaic\\Helpers\\ClassNameHelper',
];

/**
 * Detect which class storage this Mosaic install uses, by feature rather than
 * by version: the current model needs its four tables AND its manager classes;
 * the legacy model is recognised by its old utility-class table or manager.
 *
 * Cached per blog for the request — the answer only changes when Mosaic runs
 * its data upgrade, which never happens mid-request.
 *
 * @return 'current'|'legacy'|'unavailable'
 */
function mo_class_storage(): string
{
    /** @var array<int, 'current'|'legacy'|'unavailable'> $cache */
    static $cache = [];
    $blog = (int) get_current_blog_id();
    if (!isset($cache[$blog])) {
        $cache[$blog] = mo_detect_class_storage();
    }
    return $cache[$blog];
}

/**
 * @return 'current'|'legacy'|'unavailable'
 */
function mo_detect_class_storage(): string
{
    if (mo_all_classes_exist(MO_CLASS_MODEL_CLASSES) && mo_all_tables_exist(MO_CLASS_MODEL_TABLES)) {
        return 'current';
    }
    $legacy =
        mo_table_exists('utility_classes')
        || class_exists('Mosaic\\EditorInstance\\MResources\\UtilityClasses\\UtilityClassesMResourceManager');
    return $legacy ? 'legacy' : 'unavailable';
}

/**
 * @param list<string> $classes
 */
function mo_all_classes_exist(array $classes): bool
{
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            return false;
        }
    }
    return true;
}

/**
 * @param list<string> $entities
 */
function mo_all_tables_exist(array $entities): bool
{
    foreach ($entities as $entity) {
        if (!mo_table_exists($entity)) {
            return false;
        }
    }
    return true;
}

function mo_table_exists(string $entity): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $name = mo_table($entity);
    /** @var mixed $found */
    // @mago-expect analysis:possibly-invalid-argument
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name)));
    return is_string($found) && $found === $name;
}

/**
 * Pre-flight for every class- or variant-dependent path. Returns null when the
 * class model is available, otherwise an actionable error. Agent-facing text
 * stays version-agnostic: it describes what was found on the site.
 */
function mo_require_class_model(): ?WP_Error
{
    $storage = mo_class_storage();
    if ($storage === 'current') {
        return null;
    }
    if ($storage === 'legacy') {
        return mo_error('mo_legacy_class_storage', __(
            'This Mosaic install still stores its classes in the legacy format (utility classes and element classes), which the Novamira class and variant tools do not support. Update Mosaic to its latest release and let it finish its data upgrade, then retry. Themes, templates, elements, components, collections and variables keep working in the meantime; only Classes, Variants and element class inputs are affected.',
            domain: 'novamira-pro',
        ));
    }
    return mo_error('mo_runtime_unavailable', __(
        'Mosaic\'s class storage was not found on this site. Open the Mosaic admin once so it can finish setting up its tables, then retry.',
        domain: 'novamira-pro',
    ));
}

/**
 * Typed not-found for a missing or soft-deleted theme.
 */
function mo_require_publish_theme(string $theme_id): ?WP_Error
{
    $theme = $theme_id === '' ? null : mo_load('themes', $theme_id);
    if ($theme !== null && ($theme['status'] ?? '') === 'publish') {
        return null;
    }
    return mo_error('mo_not_found', sprintf(
        /* translators: %s: theme UUID. */
        __('Mosaic theme %s not found.', domain: 'novamira-pro'),
        $theme_id,
    ));
}

// ---------------------------------------------------------------------------
// Theme class index
// ---------------------------------------------------------------------------

/**
 * Load every published Class, sub class and variant sub class of a theme and
 * derive the names each one emits. Built once per call and handed to the
 * resolvers, the guards and the presenters, so an element write costs three
 * SELECTs no matter how many classes it touches.
 *
 * Entries are `array{id, kind, name, label, legacy_class_name, emitted_name,
 * parent_type, parent_id, root_id, ordering, theme_id, revision, data}` (see
 * {@see mo_class_entry()}). Emitted names come from the `emittedName` column
 * Mosaic projects on save; a row without one (written by something other than
 * Mosaic's managers) gets the same name derived here with Mosaic's own
 * ClassNameHelper. A sub class whose parent chain does not reach a published
 * root is an orphan: Mosaic neither renders nor projects it, so it is left out.
 *
 * @return array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>}
 */
function mo_class_index(string $theme_id): array
{
    $classes = [];
    $class_segments = [];
    foreach (mo_class_rows('universal_classes', $theme_id) as $row) {
        $entry = mo_class_entry($row, kind: 'class');
        $segment = mo_sanitize_root_segment($entry['name']);
        if ($entry['emitted_name'] === '') {
            $entry['emitted_name'] = mo_join_class_segments([$segment]);
        }
        $classes[$entry['id']] = $entry;
        $class_segments[$entry['id']] = $segment;
    }
    $variant_segments = [];
    foreach (mo_variant_catalog() as $id => $variant) {
        $variant_segments[$id] = mo_sanitize_sub_segment($variant['class_name']);
    }
    return [
        'classes' => $classes,
        'sub_classes' => mo_chain_entries(
            mo_class_rows('universal_sub_classes', $theme_id),
            kind: 'sub_class',
            root_type: 'universalClass',
            root_segments: $class_segments,
        ),
        'variant_sub_classes' => mo_chain_entries(
            mo_class_rows('variant_sub_classes', $theme_id),
            kind: 'variant_sub_class',
            root_type: 'variant',
            root_segments: $variant_segments,
        ),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function mo_class_rows(string $entity, string $theme_id): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table($entity);
    // @mago-expect analysis:possibly-invalid-argument
    $sql = (string) $wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE `themeID` = %s AND `status` = 'publish' ORDER BY BINARY `ordering` ASC",
        $theme_id,
    );
    /** @var mixed $rows */
    $rows = $wpdb->get_results($sql, 'ARRAY_A');
    /** @var list<array<string, mixed>> */
    return is_array($rows) ? $rows : [];
}

/**
 * @param array<string, mixed> $row
 * @return array{id:string, kind:string, name:string, label:string, legacy_class_name:string, emitted_name:string, parent_type:string, parent_id:string, root_id:string, ordering:string, theme_id:string, revision:string, data:array<array-key, mixed>}
 */
function mo_class_entry(array $row, string $kind): array
{
    $data = mo_decode_data($row['data'] ?? null);
    return [
        'id' => mo_norm_uuid($row['ID'] ?? null),
        'kind' => $kind,
        'name' => is_string($data['name'] ?? null) ? $data['name'] : '',
        'label' => is_string($data['label'] ?? null) ? $data['label'] : '',
        'legacy_class_name' => is_string($data['legacyClassName'] ?? null) ? $data['legacyClassName'] : '',
        'emitted_name' => mo_str($row, key: 'emittedName'),
        'parent_type' => mo_str($row, key: 'parentType'),
        'parent_id' => mo_norm_uuid($row['parentID'] ?? null),
        'root_id' => '',
        'ordering' => mo_str($row, key: 'ordering'),
        'theme_id' => mo_str($row, key: 'themeID'),
        'revision' => mo_str($row, key: 'revision'),
        'data' => $data,
    ];
}

/**
 * Build the entries of a sub-class family, dropping orphans and deriving the
 * compound emitted name where Mosaic has not projected one yet.
 *
 * @param list<array<string, mixed>> $rows
 * @param array<string, string>      $root_segments root UUID → its sanitized segment (published roots only)
 * @return array<string, array<string, mixed>>
 */
function mo_chain_entries(array $rows, string $kind, string $root_type, array $root_segments): array
{
    $by_id = [];
    foreach ($rows as $row) {
        $entry = mo_class_entry($row, $kind);
        $by_id[$entry['id']] = $entry;
    }
    $out = [];
    foreach ($by_id as $id => $entry) {
        $chain = mo_walk_to_root($by_id, $entry, $root_type);
        if ($chain === null || !isset($root_segments[$chain['root_id']])) {
            continue;
        }
        $entry['root_id'] = $chain['root_id'];
        if ($entry['emitted_name'] === '') {
            $entry['emitted_name'] = mo_join_class_segments([$root_segments[$chain['root_id']], ...$chain['segments']]);
        }
        $out[$id] = $entry;
    }
    return $out;
}

/**
 * Walk a sub-class entry up to its root. Returns the root id and the sanitized
 * segments from the first level below the root down to `$entry`, or null when
 * the chain breaks (a missing parent: an orphan).
 *
 * @param array<string, array{id:string, kind:string, name:string, label:string, legacy_class_name:string, emitted_name:string, parent_type:string, parent_id:string, root_id:string, ordering:string, theme_id:string, revision:string, data:array<array-key, mixed>}> $by_id
 * @param array{id:string, kind:string, name:string, label:string, legacy_class_name:string, emitted_name:string, parent_type:string, parent_id:string, root_id:string, ordering:string, theme_id:string, revision:string, data:array<array-key, mixed>} $entry
 * @return array{root_id:string, segments:list<string>}|null
 */
function mo_walk_to_root(array $by_id, array $entry, string $root_type): ?array
{
    $segments = [];
    $cursor = $entry;
    for ($guard = 0; $guard < 64; $guard++) {
        $segments[] = mo_sanitize_sub_segment($cursor['name']);
        if ($cursor['parent_type'] === $root_type) {
            return ['root_id' => $cursor['parent_id'], 'segments' => array_reverse($segments)];
        }
        $parent = $by_id[$cursor['parent_id']] ?? null;
        if ($parent === null) {
            return null;
        }
        $cursor = $parent;
    }
    return null;
}

/**
 * Sanitize a Class (root) name segment with Mosaic's own helper: strips the
 * reserved `m-` prefix and requires a leading letter. '' when unusable.
 */
function mo_sanitize_root_segment(string $name): string
{
    /** @var mixed $segment */
    // Mosaic's ClassNameHelper::sanitizeSegment($input, $isRoot) — positional.
    // @mago-expect lint:literal-named-argument
    $segment = mo_call(['Mosaic\\Helpers\\ClassNameHelper', 'sanitizeSegment'], $name, true);
    return is_string($segment) ? $segment : '';
}

/**
 * Sanitize a sub-class name segment (or a Variant's catalog class name, which
 * Mosaic also sanitizes as a non-root segment). '' when unusable.
 */
function mo_sanitize_sub_segment(string $name): string
{
    /** @var mixed $segment */
    $segment = mo_call(['Mosaic\\Helpers\\ClassNameHelper', 'sanitizeSegment'], $name);
    return is_string($segment) ? $segment : '';
}

/**
 * Join sanitized segments into the emitted compound name exactly like Mosaic:
 * `--` separated, with the `class` fallback for any empty level.
 *
 * @param list<string> $segments root first
 */
function mo_join_class_segments(array $segments): string
{
    /** @var mixed $full */
    $full = mo_call(['Mosaic\\Helpers\\ClassNameHelper', 'deriveFallbackFullName'], $segments);
    return is_string($full) ? $full : '';
}

/**
 * True when Mosaic's sanitizer drops letters or digits from `$name` (e.g. a
 * non-Latin script). Mosaic keeps the original text as the record's `label`
 * in that case, and so do we.
 */
function mo_class_name_is_lossy(string $name): bool
{
    return mo_call(['Mosaic\\Helpers\\ClassNameHelper', 'isLossyTransliteration'], $name) === true;
}

/**
 * The built-in Variant catalog, keyed by UUID. Read straight from the JSON file
 * Mosaic's own VariantCatalogRevisionModel loads (it lives next to that class),
 * so the list always matches the installed Mosaic.
 *
 * @return array<string, array{id:string, name:string, class_name:string, type:string, editable:bool}>
 */
function mo_variant_catalog(): array
{
    /** @var array<string, array{id:string, name:string, class_name:string, type:string, editable:bool}>|null $cache */
    static $cache = null;
    if ($cache === null) {
        $cache = mo_read_variant_catalog();
    }
    return $cache;
}

/**
 * @return array<string, array{id:string, name:string, class_name:string, type:string, editable:bool}>
 */
function mo_read_variant_catalog(): array
{
    $model = 'Mosaic\\VariantCatalog\\VariantCatalogRevisionModel';
    $file = class_exists($model) ? (new \ReflectionClass($model))->getFileName() : false;
    $json = is_string($file) ? file_get_contents(dirname($file) . '/VariantCatalog.json') : false;
    /** @var mixed $decoded */
    $decoded = is_string($json) ? json_decode($json, associative: true) : null;
    $catalog = [];
    /** @var mixed $item */
    foreach (is_array($decoded) ? $decoded : [] as $item) {
        if (!is_array($item) || !is_string($item['uuid'] ?? null)) {
            continue;
        }
        $id = strtolower($item['uuid']);
        $catalog[$id] = [
            'id' => $id,
            'name' => is_string($item['name'] ?? null) ? $item['name'] : '',
            'class_name' => is_string($item['className'] ?? null) ? $item['className'] : '',
            'type' => is_string($item['type'] ?? null) ? $item['type'] : '',
            'editable' => ($item['isEditable'] ?? true) !== false,
        ];
    }
    return $catalog;
}

/**
 * The Variants Mosaic's editor lets authors extend: editable catalog entries
 * that are not bare HTML-tag styles (the same predicate as
 * VariantCatalogMResourceManager::getFavorableVariantMResources() :81-85).
 *
 * @param array{id:string, name:string, class_name:string, type:string, editable:bool} $meta
 */
function mo_variant_accepts_sub_classes(array $meta): bool
{
    return $meta['editable'] && $meta['type'] !== 'tag';
}

/**
 * The Variants an element type supports, default first, straight from Mosaic's
 * element factory (NodeFactoryManager::getInstance()->getType()).
 * ElementTypeFactoryAbstract keeps the set in its protected `supportedVariants`
 * map (filled by addSupportedVariant() in each factory's init()) and exposes
 * no public getter; an object-to-array cast reads it without reflection (a
 * protected property surfaces under the "\0*\0<name>" key). The default comes
 * from the public getDefaultVariantID().
 *
 * @return array{default:string, variants:list<string>}|WP_Error
 */
function mo_element_type_variants(string $element_type): array|WP_Error
{
    $factory = mo_element_type_factory(explode('/', $element_type, limit: 2)[0]);
    /** @var mixed $supported */
    // The cast is the point: it is what exposes the protected property.
    // @mago-expect analysis:invalid-type-cast
    $supported = $factory === null ? null : ((array) $factory)["\0*\0supportedVariants"] ?? null;
    /** @var mixed $default */
    $default = $factory === null ? null : mo_call([$factory, 'getDefaultVariantID']);
    $variants = [];
    foreach (is_array($supported) ? array_keys($supported) : [] as $id) {
        $variants[] = strtolower((string) $id);
    }
    if (!is_string($default) || $variants === []) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: Mosaic element type. */
            __('Mosaic element type `%s` has no Variants.', domain: 'novamira-pro'),
            $element_type,
        ));
    }
    return ['default' => strtolower($default), 'variants' => $variants];
}

/**
 * The element factory Mosaic registered for a node type, or null for a type
 * that is not an element (NodeFactoryManager::getType() hands back its
 * "unknown" factory for anything unregistered).
 */
function mo_element_type_factory(string $type): ?object
{
    /** @var mixed $manager */
    $manager = mo_call(['Mosaic\\EditorInstance\\DocumentInstance\\NodeFactoryManager', 'getInstance']);
    /** @var mixed $factory */
    $factory = is_object($manager) ? mo_call([$manager, 'getType'], $type) : null;
    $abstract = 'Mosaic\\NodeTypes\\ElementAbstract\\ElementTypeFactoryAbstract';
    return is_object($factory) && is_a($factory, $abstract) ? $factory : null;
}

// ---------------------------------------------------------------------------
// Reference resolution
// ---------------------------------------------------------------------------

function mo_looks_like_uuid(string $value): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
}

/**
 * Find the entry a reference names inside a set of families: by id when the
 * reference is a UUID, otherwise by the (lowercased) name it emits. Emitted
 * names are unique per theme (Mosaic's UNIQUE(themeID, emittedName) index),
 * and a root name can never contain `--`, so a name never matches twice.
 *
 * @param list<array<string, array<string, mixed>>> $families
 * @return array<string, mixed>|null
 */
function mo_find_class_entry(array $families, string $ref): ?array
{
    $needle = strtolower(trim($ref));
    $by_id = mo_looks_like_uuid($needle);
    foreach ($families as $family) {
        $hit = $by_id ? $family[$needle] ?? null : mo_find_entry_by_emitted_name($family, $needle);
        if ($hit !== null) {
            return $hit;
        }
    }
    return null;
}

/**
 * @param array<string, array<string, mixed>> $family
 * @return array<string, mixed>|null
 */
function mo_find_entry_by_emitted_name(array $family, string $name): ?array
{
    foreach ($family as $entry) {
        if ($entry['emitted_name'] === $name) {
            return $entry;
        }
    }
    return null;
}

/**
 * Resolve a Class / sub class reference — a UUID or the name it emits (e.g.
 * `card`, `card--large`) — to the `{root, attached}` pair that
 * `style.universalClasses` stores.
 *
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array{root:string, attached:string}|WP_Error
 */
function mo_resolve_class_ref(array $index, string $ref): array|WP_Error
{
    $entry = mo_find_class_entry([$index['classes'], $index['sub_classes']], $ref);
    if ($entry !== null) {
        $root_key = $entry['kind'] === 'class' ? 'id' : 'root_id';
        return ['root' => (string) $entry[$root_key], 'attached' => (string) $entry['id']];
    }
    $hint = mo_resolve_variant_ref($index, $ref) instanceof WP_Error
        ? ''
        : ' ' . __('It is a Variant — select it with `variant` instead.', domain: 'novamira-pro');
    return mo_error('mo_not_found', sprintf(
        /* translators: 1: the requested class id or name, 2: hint. */
        __(
            'Mosaic Class or sub class `%1$s` was not found in this theme. Pass a UUID or emitted name from mosaic-list-classes / mosaic-get-class.%2$s',
            domain: 'novamira-pro',
        ),
        trim($ref),
        $hint,
    ));
}

/**
 * Resolve a Variant reference — a Variant UUID or catalog class name (e.g.
 * `m-link`), or a variant sub class UUID or emitted name (e.g.
 * `m-button--primary`) — to the `{variant, sub_class}` pair `style.variant`
 * encodes. `sub_class` is '' when the plain Variant was chosen.
 *
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array{variant:string, sub_class:string}|WP_Error
 */
function mo_resolve_variant_ref(array $index, string $ref): array|WP_Error
{
    $variant = mo_find_catalog_variant(trim($ref));
    if ($variant !== null) {
        return ['variant' => $variant, 'sub_class' => ''];
    }
    $sub = mo_find_class_entry([$index['variant_sub_classes']], $ref);
    if ($sub !== null) {
        return ['variant' => (string) $sub['root_id'], 'sub_class' => (string) $sub['id']];
    }
    return mo_error('mo_not_found', sprintf(
        /* translators: %s: the requested variant id or name. */
        __(
            'Mosaic Variant or variant sub class `%s` was not found. Pass a UUID or class name from mosaic-list-variants.',
            domain: 'novamira-pro',
        ),
        trim($ref),
    ));
}

/**
 * Catalog Variant id for a UUID or catalog class name, or null.
 */
function mo_find_catalog_variant(string $ref): ?string
{
    $needle = strtolower($ref);
    $catalog = mo_variant_catalog();
    if (isset($catalog[$needle])) {
        return $needle;
    }
    foreach ($catalog as $id => $variant) {
        if ($variant['class_name'] !== '' && $variant['class_name'] === $needle) {
            return $id;
        }
    }
    return null;
}

/**
 * Every class name Mosaic owns in a theme, mapped to what the agent should use
 * instead: `classes` for Classes / sub classes (including the legacy alias a
 * migrated class still renders), `variant` for Variants and variant sub
 * classes. Used to keep Mosaic classes out of `data.cssClasses`, where they
 * would render without their CSS.
 *
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array<string, 'classes'|'variant'>
 */
function mo_mosaic_owned_class_names(array $index): array
{
    $owned = [];
    foreach (mo_variant_catalog() as $variant) {
        $owned[$variant['class_name']] = 'variant';
    }
    foreach (mo_family_class_names($index['variant_sub_classes']) as $name) {
        $owned[$name] = 'variant';
    }
    $class_entries = [...array_values($index['classes']), ...array_values($index['sub_classes'])];
    foreach (mo_family_class_names($class_entries) as $name) {
        $owned[$name] = 'classes';
    }
    unset($owned['']);
    return $owned;
}

/**
 * Emitted names plus legacy aliases of a list of entries.
 *
 * @param array<array-key, array<string, mixed>> $entries
 * @return list<string>
 */
function mo_family_class_names(array $entries): array
{
    $names = [];
    foreach ($entries as $entry) {
        $names[] = (string) $entry['emitted_name'];
        $names[] = (string) $entry['legacy_class_name'];
    }
    return $names;
}

// ---------------------------------------------------------------------------
// Presentation
// ---------------------------------------------------------------------------

/**
 * Compact nested tree of the published sub classes under `$parent_id`.
 *
 * @param array<string, array<string, mixed>> $family
 * @return list<array<string, mixed>>
 */
function mo_class_subtree(array $family, string $parent_id, int $depth = 0): array
{
    $out = [];
    foreach ($depth > 32 ? [] : $family as $entry) {
        if ($entry['parent_id'] !== $parent_id) {
            continue;
        }
        $out[] = [
            'id' => $entry['id'],
            'name' => $entry['name'],
            'emitted_name' => $entry['emitted_name'],
            'sub_classes' => mo_class_subtree($family, (string) $entry['id'], $depth + 1),
        ];
    }
    return $out;
}

/**
 * @param array<string, array<string, mixed>> $family
 */
function mo_class_descendant_count(array $family, string $parent_id): int
{
    $count = 0;
    foreach ($family as $entry) {
        if ($entry['parent_id'] !== $parent_id) {
            continue;
        }
        $count += 1 + mo_class_descendant_count($family, (string) $entry['id']);
    }
    return $count;
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function mo_present_class_summary(array $entry, int $sub_class_count): array
{
    return [
        'id' => $entry['id'],
        'theme_id' => $entry['theme_id'],
        'name' => $entry['name'],
        'label' => $entry['label'],
        'emitted_name' => $entry['emitted_name'],
        'ordering' => $entry['ordering'],
        'sub_class_count' => $sub_class_count,
    ];
}

/**
 * Full record for a Class, sub class or variant sub class. `sub_classes` is the
 * compact tree below it; `data` is the decoded Mosaic blob (`states`,
 * `localStates`, `interactions`, …).
 *
 * @param array{classes: array<string, array<string, mixed>>, sub_classes: array<string, array<string, mixed>>, variant_sub_classes: array<string, array<string, mixed>>} $index
 * @return array<string, mixed>|null
 */
function mo_present_class_record(array $index, string $kind, string $id): ?array
{
    $family = $index[mo_class_family($kind)['index']];
    $entry = $family[$id] ?? null;
    if ($entry === null) {
        return null;
    }
    $record = [
        'id' => $entry['id'],
        'theme_id' => $entry['theme_id'],
        'name' => $entry['name'],
        'label' => $entry['label'],
        'emitted_name' => $entry['emitted_name'],
        'legacy_class_name' => $entry['legacy_class_name'],
        'ordering' => $entry['ordering'],
        'revision' => $entry['revision'],
    ];
    $record += $kind === 'class' ? mo_class_only_fields($entry) : mo_sub_class_only_fields($entry, $kind);
    $children = $kind === 'variant_sub_class' ? $index['variant_sub_classes'] : $index['sub_classes'];
    $record['sub_classes'] = mo_class_subtree($children, $id);
    $record['data'] = $entry['data'];
    return $record;
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function mo_class_only_fields(array $entry): array
{
    $data = is_array($entry['data']) ? $entry['data'] : [];
    $favored = is_array($data['favoredVariants'] ?? null) ? array_values($data['favoredVariants']) : [];
    return ['favored_variants' => $favored];
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function mo_sub_class_only_fields(array $entry, string $kind): array
{
    $is_variant = $kind === 'variant_sub_class';
    $parent_is_root = $entry['parent_type'] === 'universalClass' || $entry['parent_type'] === 'variant';
    $root_label = $is_variant ? 'variant' : 'class';
    return [
        $is_variant ? 'variant_id' : 'class_id' => $entry['root_id'],
        'parent_id' => $entry['parent_id'],
        'parent_type' => $parent_is_root ? $root_label : $kind,
    ];
}

// ---------------------------------------------------------------------------
// Mosaic manager facades
// ---------------------------------------------------------------------------

/**
 * Table, index key and manager type per family. Manager keys are the MResource
 * `type` constants Mosaic registers its managers under
 * (EditorInstanceAbstract::getMResourceManager()).
 *
 * @return array{table:string, index:'classes'|'sub_classes'|'variant_sub_classes', manager:string, label:string}
 */
function mo_class_family(string $kind): array
{
    return match ($kind) {
        'class' => [
            'table' => 'universal_classes',
            'index' => 'classes',
            'manager' => 'universalClass',
            'label' => 'Class',
        ],
        'sub_class' => [
            'table' => 'universal_sub_classes',
            'index' => 'sub_classes',
            'manager' => 'universalSubClass',
            'label' => 'sub class',
        ],
        default => [
            'table' => 'variant_sub_classes',
            'index' => 'variant_sub_classes',
            'manager' => 'variantSubClass',
            'label' => 'variant sub class',
        ],
    };
}

/**
 * Class-exists pre-flight for the class facades, so a Mosaic build without
 * the class model fails with a typed error instead of a fatal.
 */
function mo_require_class_model_classes(): ?WP_Error
{
    foreach (MO_CLASS_MODEL_CLASSES as $class) {
        if (!class_exists($class)) {
            return mo_error('mo_runtime_unavailable', sprintf(
                /* translators: %s: Mosaic PHP class FQN. */
                __(
                    'Mosaic class %s is missing, so Mosaic Classes cannot be written on this install.',
                    domain: 'novamira-pro',
                ),
                $class,
            ));
        }
    }
    return null;
}

function mo_editor_manager(object $editor, string $type): object
{
    /** @var mixed $manager */
    $manager = mo_call([$editor, 'getMResourceManager'], $type);
    if (!is_object($manager)) {
        throw new \UnexpectedValueException("Mosaic editor exposes no {$type} manager.");
    }
    return $manager;
}

/**
 * Published MResource by id, or null.
 */
function mo_editor_resource(object $manager, string $id): ?object
{
    /** @var mixed $resource */
    $resource = mo_call([$manager, 'getMResourceByID'], $id, ['publish']);
    return is_object($resource) ? $resource : null;
}

/**
 * The editor's detacher of a given type (MResourcesDetacherManager), or null.
 */
function mo_editor_detacher(object $editor, string $type): ?object
{
    /** @var mixed $detachers */
    $detachers = method_exists($editor, 'getMResourcesDetacherManager')
        ? mo_call([$editor, 'getMResourcesDetacherManager'])
        : null;
    /** @var mixed $detacher */
    $detacher = is_object($detachers) ? mo_call([$detachers, 'getMResourceDetacher'], $type) : null;
    return is_object($detacher) ? $detacher : null;
}

/**
 * Store one data property through Mosaic's validator. Returns a typed error
 * carrying Mosaic's message when the value is rejected (the previous value
 * survives in that case).
 */
function mo_store_resource_value(object $resource, string $property, mixed $value): ?WP_Error
{
    /** @var mixed $data */
    $data = mo_call([$resource, 'getData']);
    /** @var mixed $result */
    $result = is_object($data) ? mo_call([$data, 'store'], $property, $value) : null;
    if (is_object($result) && mo_call([$result, 'isValid']) === true) {
        return null;
    }
    /** @var mixed $message */
    $message = is_object($result) ? mo_call([$result, 'getErrorMessage']) : null;
    return mo_error('mo_invalid_input', sprintf(
        /* translators: 1: property name, 2: upstream Mosaic validation message. */
        __('Mosaic rejected `%1$s`: %2$s', domain: 'novamira-pro'),
        $property,
        is_string($message) && $message !== '' ? $message : __('invalid value', domain: 'novamira-pro'),
    ));
}

/**
 * Validate a class-name input against Mosaic's grammar before handing it to
 * Mosaic, so a name that would be silently replaced by the `class` fallback is
 * refused with an explanation instead.
 */
function mo_validate_class_name_input(string $name, string $kind): ?WP_Error
{
    $segment = $kind === 'class' ? mo_sanitize_root_segment($name) : mo_sanitize_sub_segment($name);
    if ($segment !== '') {
        return null;
    }
    return mo_error('mo_invalid_input', sprintf(
        /* translators: %s: rejected class name. */
        __(
            'Class name `%s` has nothing Mosaic can use as a class name. Use letters, digits, `-` or `_`; a Class name must start with a letter (a leading `m-` is reserved for Mosaic and stripped). Mosaic lowercases the name and turns spaces into `-` (e.g. "Card Large" → `card-large`).',
            domain: 'novamira-pro',
        ),
        $name,
    ));
}

/**
 * Create a Class, sub class or variant sub class through the matching Mosaic
 * manager's restore*MResource() entry point. Both sub-class restore methods
 * return null even on success (UniversalSubClassesMResourceManager
 * ::restoreUniversalSubClassMResource(), VariantSubClassesMResourceManager
 * :99-141), so success is checked by looking the new id up afterwards.
 *
 * @param array{kind:string, theme_id:string, name:string, label:string, parent_type:string, parent_id:string} $spec
 * @return string|WP_Error the new id
 */
function mo_facade_create_class(array $spec): string|WP_Error
{
    $err = mo_require_class_model_classes();
    if ($err !== null) {
        return $err;
    }
    $id = mo_uuid();
    $record = mo_class_restore_record($spec, $id);
    $method = match ($spec['kind']) {
        'class' => 'restoreUniversalClassMResource',
        'sub_class' => 'restoreUniversalSubClassMResource',
        default => 'restoreVariantSubClassMResource',
    };
    return mo_facade_editor_write($spec['theme_id'], static function (object $editor) use (
        $spec,
        $record,
        $method,
        $id,
    ): string|WP_Error {
        if ($spec['parent_type'] === 'variant') {
            mo_ensure_variant_resource($editor, $spec['parent_id']);
        }
        $manager = mo_editor_manager($editor, mo_class_family($spec['kind'])['manager']);
        mo_call([$manager, $method], $record);
        if (mo_editor_resource($manager, $id) === null) {
            return mo_error('mo_facade_failed', __(
                'Mosaic did not create the class (its parent may have been removed meanwhile).',
                domain: 'novamira-pro',
            ));
        }
        return $id;
    });
}

/**
 * The revision-record plain object Mosaic's restore*MResource() expects.
 * `states` must be an object and `localStates` / `interactions` arrays
 * (DataGroup / DataArray validators).
 *
 * @param array{kind:string, theme_id:string, name:string, label:string, parent_type:string, parent_id:string} $spec
 */
function mo_class_restore_record(array $spec, string $id): object
{
    $data = [
        'name' => $spec['name'],
        'states' => new \stdClass(),
        'localStates' => [],
        'interactions' => [],
    ];
    if ($spec['label'] !== '') {
        $data['label'] = $spec['label'];
    }
    if ($spec['kind'] === 'class') {
        $data['favoredVariants'] = [];
    }
    $record = ['ID' => $id, 'data' => (object) $data];
    if ($spec['kind'] !== 'class') {
        $record['parentType'] = $spec['parent_type'];
        $record['parentID'] = $spec['parent_id'];
    }
    return (object) $record;
}

/**
 * Make sure the theme has a `variants` row for a catalog Variant before a sub
 * class is hung under it. Without the row, VariantSubClassesMResourceManager::
 * getParentMResource() hands back an MResourceUnknown placeholder and the
 * restore fatals on it. The row is created exactly the way Mosaic builds its
 * in-memory fallback (ElementMResourceAbstract::getVariantMResource()
 * :184-216) — a revision record whose ID is the catalog UUID — but added to
 * the manager so pushToDB() persists it.
 */
function mo_ensure_variant_resource(object $editor, string $variant_id): void
{
    $manager = mo_editor_manager($editor, type: 'variant');
    if (mo_call([$manager, 'getMResourceByID'], $variant_id) !== null) {
        return;
    }
    $raw = (object) ['ID' => $variant_id, 'data' => new \stdClass()];
    /** @var mixed $record */
    // Mosaic's createRevisionRecord($raw, $isNew) — positional.
    // @mago-expect lint:literal-named-argument
    $record = mo_call([$manager, 'createRevisionRecord'], $raw, true);
    /** @var mixed $factory */
    $factory = mo_call([$manager, 'getFactory']);
    /** @var mixed $resource */
    // Mosaic's MResourceFactoryAbstract::create($record, $isTrusted) — positional.
    // @mago-expect lint:literal-named-argument
    $resource = is_object($factory) ? mo_call([$factory, 'create'], $record, false) : null;
    if (!is_object($resource)) {
        throw new \UnexpectedValueException("Mosaic could not materialise Variant {$variant_id}.");
    }
    mo_call([$manager, 'addMResource'], $resource);
    mo_call([$resource, 'initSync']);
}

/**
 * Rename / relabel a class-family record through Mosaic's validators.
 *
 * @param array{kind:string, theme_id:string, id:string, name:?string, label:?string} $spec
 * @return string|WP_Error
 */
function mo_facade_edit_class(array $spec): string|WP_Error
{
    $err = mo_require_class_model_classes();
    if ($err !== null) {
        return $err;
    }
    return mo_facade_editor_write($spec['theme_id'], static function (object $editor) use ($spec): string|WP_Error {
        $family = mo_class_family($spec['kind']);
        $resource = mo_editor_resource(mo_editor_manager($editor, $family['manager']), $spec['id']);
        if ($resource === null) {
            return mo_concurrent_modification_error($family['label'], $spec['id']);
        }
        $err = $spec['name'] === null
            ? null
            : mo_store_resource_value($resource, property: 'name', value: $spec['name']);
        if ($err !== null) {
            return $err;
        }
        $label = $spec['label'] === '' ? null : $spec['label'];
        $err = $spec['label'] === null ? null : mo_store_resource_value($resource, property: 'label', value: $label);
        return $err ?? $spec['id'];
    });
}

/**
 * Delete a class-family record together with every sub class below it,
 * following Mosaic's own delete path: delete() each resource, hand it to the
 * family's detacher, pushToDB() (which removes the rows), then the post-commit
 * detach pass that rewrites element references theme-wide. See the DELETES
 * section of the file docblock.
 *
 * @return list<string>|WP_Error deleted ids
 */
function mo_facade_delete_class_branch(string $kind, string $theme_id, string $id): array|WP_Error
{
    $err = mo_require_class_model_classes();
    if ($err !== null) {
        return $err;
    }
    return mo_facade_editor_delete($theme_id, static function (object $editor) use ($kind, $id): array {
        $root = mo_editor_resource(mo_editor_manager($editor, mo_class_family($kind)['manager']), $id);
        if ($root === null) {
            return [];
        }
        $is_variant = $kind === 'variant_sub_class';
        $detacher = mo_editor_detacher(
            $editor,
            $is_variant ? 'variantSubClassDetacher' : 'universalClassUniversalSubClassDetacher',
        );
        $deleted = [];
        foreach ([
            $root,
            ...mo_editor_descendants($root, $is_variant ? 'variantSubClass' : 'universalSubClass'),
        ] as $resource) {
            mo_call([$resource, 'delete']);
            if ($detacher !== null) {
                mo_call([$detacher, 'onMResourceDeleted'], $resource);
            }
            /** @var mixed $resource_id */
            $resource_id = mo_call([$resource, 'getID']);
            $deleted[] = is_string($resource_id) ? $resource_id : '';
        }
        return $deleted;
    });
}

/**
 * Every published sub-class resource below `$resource`, depth first. Walks the
 * public children accessors of each family: UniversalClassMResource /
 * UniversalSubClassMResource::getUniversalSubClassMResources() and
 * VariantSubClassMResource::getVariantSubClassMResourcesOfVariantSubClass().
 *
 * @return list<object>
 */
function mo_editor_descendants(object $resource, string $family, int $depth = 0): array
{
    $method = $family === 'variantSubClass'
        ? 'getVariantSubClassMResourcesOfVariantSubClass'
        : 'getUniversalSubClassMResources';
    /** @var mixed $children */
    $children = $depth <= 32 && method_exists($resource, $method) ? mo_call([$resource, $method]) : [];
    $out = [];
    /** @var mixed $child */
    foreach (is_array($children) ? $children : [] as $child) {
        if (!is_object($child)) {
            continue;
        }
        $out = [...$out, $child, ...mo_editor_descendants($child, $family, $depth + 1)];
    }
    return $out;
}

/**
 * Load a published class-family row by id, or a typed not-found error.
 *
 * @return array<string, mixed>|WP_Error
 */
function mo_load_class_row(string $kind, string $id): array|WP_Error
{
    $family = mo_class_family($kind);
    $row = $id === '' ? null : mo_load($family['table'], $id);
    if ($row !== null && ($row['status'] ?? '') === 'publish') {
        return $row;
    }
    return mo_error('mo_not_found', sprintf(
        /* translators: 1: record kind (Class / sub class / variant sub class), 2: UUID. */
        __('Mosaic %1$s %2$s not found.', domain: 'novamira-pro'),
        $family['label'],
        $id,
    ));
}

/**
 * Re-read one class-family record after a write and present it in full.
 *
 * @return array<string, mixed>|WP_Error
 */
function mo_reload_class_record(string $kind, string $theme_id, string $id): array|WP_Error
{
    $record = mo_present_class_record(mo_class_index($theme_id), $kind, $id);
    if ($record === null) {
        return mo_error('mo_facade_failed', __(
            'The class was written but could not be read back as a published record.',
            domain: 'novamira-pro',
        ));
    }
    return $record;
}

/**
 * Shared get body for the three get abilities.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_get_class_record(string $kind, string $record_key, array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    $row = mo_load_class_row($kind, $id);
    if ($row instanceof WP_Error) {
        return $row;
    }
    $record = mo_present_class_record(mo_class_index(mo_str($row, key: 'themeID')), $kind, $id);
    if ($record === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: 1: record kind, 2: UUID. */
            __('Mosaic %1$s %2$s not found, or its parent no longer exists.', domain: 'novamira-pro'),
            mo_class_family($kind)['label'],
            $id,
        ));
    }
    return [$record_key => $record];
}

// ---------------------------------------------------------------------------
// Style writes (shared by Classes, sub classes, variant sub classes)
// ---------------------------------------------------------------------------

/**
 * @param array<string, mixed> $input
 * @return array{id: string, state: string, breakpoint_id: string, property: string, property_css: string, value: string}|WP_Error
 */
function mo_normalize_class_style_input(array $input): array|WP_Error
{
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id must be a UUID.', domain: 'novamira-pro'));
    }
    $strings = mo_read_class_style_strings($input);
    if ($strings instanceof WP_Error) {
        return $strings;
    }
    $state = strtolower($strings['state']) === 'normal' ? '&' : $strings['state'];
    $breakpoint_id = mo_normalize_mosaic_breakpoint_id($strings['breakpoint_id']);
    if ($breakpoint_id === '') {
        return mo_error('mo_invalid_input', __(
            'breakpoint_id must be base, tablet, mobile, or a breakpoint UUID.',
            domain: 'novamira-pro',
        ));
    }
    $resolved = mo_resolve_mosaic_style_property($strings['property']);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    return [
        'id' => $id,
        'state' => $state,
        'breakpoint_id' => $breakpoint_id,
        'property' => $resolved['property'],
        'property_css' => $resolved['property_css'],
        'value' => $strings['value'],
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{state: string, breakpoint_id: string, property: string, value: string}|WP_Error
 */
function mo_read_class_style_strings(array $input): array|WP_Error
{
    $input['state'] ??= 'hover';
    $fields = ['state', 'breakpoint_id', 'property', 'value'];
    $strings = [];
    foreach ($fields as $field) {
        // @mago-expect analysis:mixed-assignment
        $raw = $input[$field] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return mo_error('mo_invalid_input', sprintf(
                /* translators: %s: input field name. */
                __('%s must be a non-empty string.', domain: 'novamira-pro'),
                $field,
            ));
        }
        $strings[$field] = trim($raw);
    }
    $len_err = mo_validate_lengths([
        'state' => ['value' => $strings['state'], 'max' => 191],
        'property' => ['value' => $strings['property'], 'max' => 191],
        'value' => ['value' => $strings['value'], 'max' => 8192],
    ]);
    if ($len_err !== null) {
        return $len_err;
    }
    if (preg_match('/[;{}\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $strings['value'])) {
        return mo_error('mo_invalid_input', __(
            'value must be one CSS property value, without declarations, braces, or control characters.',
            domain: 'novamira-pro',
        ));
    }
    return $strings;
}

/**
 * Resolve a CSS property against Mosaic's SupportedStyleProperties registry.
 * `customDeclarations` (the free-form "custom CSS" property, registered at
 * SupportedStyleProperties.php:168) is excluded: it would let an agent write
 * raw declarations past the one-property-one-value contract.
 *
 * @return array{property: string, property_css: string}|WP_Error
 */
function mo_resolve_mosaic_style_property(string $property): array|WP_Error
{
    $registry = 'Mosaic\\Builder\\Style\\SupportedStyleProperties';
    if (!class_exists($registry)) {
        return mo_error('mo_runtime_unavailable', __(
            'Mosaic SupportedStyleProperties is unavailable.',
            domain: 'novamira-pro',
        ));
    }
    try {
        /** @var mixed $factories */
        $factories = mo_call([$registry, 'getCssProperties']);
        /** @var mixed $factory */
        foreach (is_array($factories) ? $factories : [] as $factory) {
            $match = is_object($factory) ? mo_match_style_property($factory, $property) : null;
            if ($match !== null) {
                return $match;
            }
        }
    } catch (\Throwable $e) {
        return mo_error('mo_facade_failed', sprintf(
            /* translators: %s: upstream Mosaic Throwable message. */
            __('Could not read Mosaic style properties: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }
    return mo_error('mo_invalid_input', sprintf(
        /* translators: %s: rejected CSS property. */
        __('CSS property %s is not supported by Mosaic.', domain: 'novamira-pro'),
        $property,
    ));
}

/**
 * @return array{property: string, property_css: string}|null
 */
function mo_match_style_property(object $factory, string $property): ?array
{
    /** @var mixed $stored */
    $stored = mo_call([$factory, 'getProperty']);
    /** @var mixed $css */
    $css = mo_call([$factory, 'getPropertyCSS']);
    if (!is_string($stored) || !is_string($css) || $stored === 'customDeclarations') {
        return null;
    }
    return $property === $stored || $property === $css ? ['property' => $stored, 'property_css' => $css] : null;
}

function mo_normalize_mosaic_breakpoint_id(mixed $breakpoint): string
{
    if (!is_string($breakpoint)) {
        return '';
    }
    $breakpoint = trim($breakpoint);
    $aliases = ['base' => '_', 'tablet' => '_t', 'mobile' => '_m'];
    $alias = strtolower($breakpoint);
    if (isset($aliases[$alias])) {
        return $aliases[$alias];
    }
    if (in_array($breakpoint, ['_', '_t', '_m'], strict: true)) {
        return $breakpoint;
    }
    return mo_norm_uuid($breakpoint);
}

/**
 * Set one style value on a class-family record through Mosaic's own resource
 * model: Mosaic validates the state, breakpoint, property and value, and the
 * pre-push envelope audit guarantees the object/list JSON shapes its revision
 * records expect.
 *
 * @param array{kind: string, theme_id: string, id: string, state: string, breakpoint_id: string, property: string, value: string} $style
 * @return string|WP_Error Sanitized value on success.
 */
function mo_facade_set_class_style(array $style): string|WP_Error
{
    $err = mo_require_class_model_classes();
    if ($err !== null) {
        return $err;
    }
    return mo_facade_editor_write($style['theme_id'], static function (object $editor) use ($style): string|WP_Error {
        $family = mo_class_family($style['kind']);
        $resource = mo_editor_resource(mo_editor_manager($editor, $family['manager']), $style['id']);
        if ($resource === null) {
            return mo_concurrent_modification_error($family['label'], $style['id']);
        }
        $breakpoint_data = mo_class_style_breakpoint($resource, $style);
        if ($breakpoint_data instanceof WP_Error) {
            return $breakpoint_data;
        }
        return mo_store_class_style_value($resource, $breakpoint_data, $style);
    });
}

/**
 * The Mosaic breakpoint data object for `state` × `breakpoint_id`, or a typed
 * error (a WP_Error object) when either is not registered.
 *
 * @param array{kind: string, theme_id: string, id: string, state: string, breakpoint_id: string, property: string, value: string} $style
 */
function mo_class_style_breakpoint(object $resource, array $style): object
{
    /** @var mixed $data */
    $data = mo_call([$resource, 'getData']);
    /** @var mixed $states */
    $states = is_object($data) ? mo_call([$data, 'getStyleStatesData']) : null;
    if (!is_object($states) || mo_call([$states, 'hasState'], $style['state']) !== true) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: rejected Mosaic state ID. */
            __('Mosaic state %s is not registered for classes.', domain: 'novamira-pro'),
            $style['state'],
        ));
    }
    /** @var mixed $state_data */
    $state_data = mo_call([$states, 'getState'], $style['state']);
    $has_breakpoint = is_object($state_data) && mo_call([$state_data, 'hasSubData'], $style['breakpoint_id']) === true;
    /** @var mixed $breakpoint_data */
    $breakpoint_data = $has_breakpoint ? mo_call([$state_data, 'getBreakpointData'], $style['breakpoint_id']) : null;
    if (!is_object($breakpoint_data)) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: rejected Mosaic breakpoint ID. */
            __('Mosaic breakpoint %s is not registered in this theme.', domain: 'novamira-pro'),
            $style['breakpoint_id'],
        ));
    }
    return $breakpoint_data;
}

/**
 * @param array{kind: string, theme_id: string, id: string, state: string, breakpoint_id: string, property: string, value: string} $style
 */
function mo_store_class_style_value(object $resource, object $breakpoint_data, array $style): string|WP_Error
{
    /** @var mixed $validation */
    $validation = mo_call([$breakpoint_data, 'store'], $style['property'], $style['value']);
    if (!is_object($validation) || mo_call([$validation, 'isValid']) !== true) {
        /** @var mixed $message */
        $message = is_object($validation) ? mo_call([$validation, 'getErrorMessage']) : null;
        return mo_error('mo_invalid_input', sprintf(
            /* translators: 1: CSS property name, 2: upstream Mosaic validation message. */
            __('Invalid value for Mosaic CSS property %1$s: %2$s', domain: 'novamira-pro'),
            $style['property'],
            is_string($message) && $message !== '' ? $message : __('Invalid data', domain: 'novamira-pro'),
        ));
    }
    /** @var mixed $sanitized */
    $sanitized = mo_call([$validation, 'getSanitizedValue']);
    if (!is_string($sanitized)) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: CSS property name. */
            __('Mosaic CSS property %s does not accept a scalar string value.', domain: 'novamira-pro'),
            $style['property'],
        ));
    }
    /** @var mixed $envelope */
    $envelope = mo_call([$resource, 'getRevisionEnvelopePlainObject']);
    return mo_validate_class_style_envelope($envelope, $style, $sanitized) ?? $sanitized;
}

/**
 * Guard the exact JSON contract before Mosaic persists its revision:
 * `data` / `states` / state / breakpoint are objects, while present
 * `localStates` / `interactions` values are arrays (core omits them when
 * empty). This prevents the `{}` versus `[]` mismatch that makes Mosaic's
 * typed json_decode path throw.
 *
 * @param array{kind: string, theme_id: string, id: string, state: string, breakpoint_id: string, property: string, value: string} $style
 */
function mo_validate_class_style_envelope(mixed $envelope, array $style, string $value): ?WP_Error
{
    $data = mo_extract_class_style_data($envelope);
    if ($data instanceof WP_Error) {
        return $data;
    }
    return mo_validate_class_style_path($data, $style, $value) ?? mo_validate_class_style_json($data);
}

/**
 * Returns a WP_Error object when the envelope is unsafe.
 */
function mo_extract_class_style_data(mixed $envelope): object
{
    if (!is_object($envelope)) {
        return mo_class_style_shape_error('revision envelope');
    }
    $revision = get_object_vars($envelope);
    // @mago-expect analysis:mixed-assignment
    $data = $revision['data'] ?? null;
    if (!is_object($data)) {
        return mo_class_style_shape_error('data');
    }
    $data_values = get_object_vars($data);
    // @mago-expect analysis:mixed-assignment
    $states = $data_values['states'] ?? null;
    if (
        !is_object($states)
        || array_key_exists('localStates', $data_values) && !is_array($data_values['localStates'])
        || array_key_exists('interactions', $data_values) && !is_array($data_values['interactions'])
    ) {
        return mo_class_style_shape_error('states / localStates / interactions');
    }
    return $data;
}

/**
 * @param array{kind: string, theme_id: string, id: string, state: string, breakpoint_id: string, property: string, value: string} $style
 */
function mo_validate_class_style_path(object $data, array $style, string $value): ?WP_Error
{
    $cursor = $data;
    $walked = [];
    foreach (['states', $style['state'], $style['breakpoint_id']] as $segment) {
        $walked[] = $segment;
        // @mago-expect analysis:mixed-assignment
        $next = get_object_vars($cursor)[$segment] ?? null;
        if (!is_object($next)) {
            return mo_class_style_shape_error(implode('.', $walked));
        }
        $cursor = $next;
    }
    // @mago-expect analysis:mixed-assignment
    $stored = get_object_vars($cursor)[$style['property']] ?? null;
    if (!is_string($stored) || $stored !== $value) {
        return mo_class_style_shape_error(implode('.', [...$walked, $style['property']]));
    }
    return null;
}

function mo_validate_class_style_json(object $data): ?WP_Error
{
    $json = wp_json_encode($data, flags: 0, depth: MO_BLOB_MAX_DEPTH);
    if (!is_string($json)) {
        return mo_class_style_shape_error('JSON encoding');
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $json, associative: true, depth: MO_BLOB_MAX_DEPTH);
    if (!is_array($decoded)) {
        return mo_class_style_shape_error('JSON round trip');
    }
    return (
        mo_validate_blob_depth($decoded, field: 'class.data') ?? mo_validate_blob_bytes($decoded, field: 'class.data')
    );
}

function mo_class_style_shape_error(string $field): WP_Error
{
    return mo_error('mo_facade_failed', sprintf(
        /* translators: %s: invalid portion of the Mosaic revision envelope. */
        __('Mosaic produced an unsafe class JSON shape at %s; no data was written.', domain: 'novamira-pro'),
        $field,
    ));
}

// ---------------------------------------------------------------------------
// Shared schemas + ability bodies
// ---------------------------------------------------------------------------

/**
 * Shared input schema for the three set-*-style abilities.
 *
 * @return array<string, mixed>
 */
function mo_class_style_input_schema(string $id_description): array
{
    return [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => $id_description],
            'state' => [
                'type' => 'string',
                'description' => 'Mosaic state ID. Defaults to `hover`; `normal` aliases Mosaic\'s base state `&`.',
                'default' => 'hover',
            ],
            'breakpoint_id' => [
                'type' => 'string',
                'description' => 'Mosaic breakpoint UUID, or `base`, `tablet`, `mobile` for `_`, `_t`, `_m`.',
            ],
            'property' => [
                'type' => 'string',
                'description' => 'Supported CSS property, for example `background-color` or `backgroundColor`.',
            ],
            'value' => [
                'type' => 'string',
                'description' => 'One CSS property value (a `var(--token)` works). Declarations containing `;`, `{`, or `}` are rejected.',
            ],
        ],
        'required' => ['id', 'breakpoint_id', 'property', 'value'],
        'additionalProperties' => false,
    ];
}

/**
 * @return array<string, mixed>
 */
function mo_class_style_output_schema(string $record_key): array
{
    return [
        'type' => 'object',
        'properties' => [
            $record_key => ['type' => 'object'],
            'style' => [
                'type' => 'object',
                'properties' => [
                    'state' => ['type' => 'string'],
                    'breakpoint_id' => ['type' => 'string'],
                    'property' => ['type' => 'string'],
                    'value' => ['type' => 'string'],
                ],
                'required' => ['state', 'breakpoint_id', 'property', 'value'],
                'additionalProperties' => false,
            ],
        ],
        'required' => [$record_key, 'style'],
        'additionalProperties' => false,
    ];
}

/**
 * @return array<string, mixed>
 */
function mo_class_delete_output_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
            'deleted_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['id', 'deleted', 'deleted_ids'],
        'additionalProperties' => false,
    ];
}

/**
 * Shared callback body for the three set-*-style abilities.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_set_class_style(string $kind, string $record_key, string $ability, array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $style = mo_normalize_class_style_input($input);
    if ($style instanceof WP_Error) {
        return $style;
    }
    $row = mo_load_class_row($kind, $style['id']);
    if ($row instanceof WP_Error) {
        return $row;
    }
    $theme_id = mo_str($row, key: 'themeID');
    $ctx = ['kind' => $kind, 'record_key' => $record_key, 'ability' => $ability, 'theme_id' => $theme_id];
    return mo_with_lock("class-write:{$theme_id}", static fn(): array|WP_Error => mo_set_class_style_locked(
        $ctx,
        $style,
    ));
}

/**
 * @param array{kind:string, record_key:string, ability:string, theme_id:string} $ctx
 * @param array{id: string, state: string, breakpoint_id: string, property: string, property_css: string, value: string} $style
 * @return array<string, mixed>|WP_Error
 */
function mo_set_class_style_locked(array $ctx, array $style): array|WP_Error
{
    $stored = mo_facade_set_class_style([
        'kind' => $ctx['kind'],
        'theme_id' => $ctx['theme_id'],
        'id' => $style['id'],
        'state' => $style['state'],
        'breakpoint_id' => $style['breakpoint_id'],
        'property' => $style['property'],
        'value' => $style['value'],
    ]);
    if ($stored instanceof WP_Error) {
        return $stored;
    }
    $record = mo_reload_class_record($ctx['kind'], $ctx['theme_id'], $style['id']);
    if ($record instanceof WP_Error) {
        return $record;
    }
    mo_emit_changed(
        ability: $ctx['ability'],
        operation: 'set',
        entity: $ctx['kind'],
        entity_id: $style['id'],
        context: ['theme_id' => $ctx['theme_id'], 'property' => $style['property_css']],
    );
    return [
        $ctx['record_key'] => $record,
        'style' => [
            'state' => $style['state'],
            'breakpoint_id' => $style['breakpoint_id'],
            'property' => $style['property_css'],
            'value' => $stored,
        ],
    ];
}

/**
 * Shared callback body for the three delete abilities.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_delete_class_record(string $kind, string $ability, array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_load(mo_class_family($kind)['table'], $id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return ['id' => $id, 'deleted' => false, 'deleted_ids' => []];
    }
    $theme_id = mo_str($row, key: 'themeID');
    return mo_with_lock("class-write:{$theme_id}", static fn(): array|WP_Error => mo_delete_class_record_locked(
        $kind,
        $ability,
        $id,
        $theme_id,
    ));
}

/**
 * @return array<string, mixed>|WP_Error
 */
function mo_delete_class_record_locked(string $kind, string $ability, string $id, string $theme_id): array|WP_Error
{
    $deleted = mo_facade_delete_class_branch($kind, $theme_id, $id);
    if ($deleted instanceof WP_Error) {
        return $deleted;
    }
    $still = mo_load(mo_class_family($kind)['table'], $id);
    if ($still !== null && ($still['status'] ?? '') === 'publish') {
        return mo_error('mo_write_failed', __('Mosaic did not delete the class.', domain: 'novamira-pro'));
    }
    if ($deleted !== []) {
        mo_emit_changed(ability: $ability, operation: 'delete', entity: $kind, entity_id: $id, context: [
            'theme_id' => $theme_id,
        ]);
    }
    return ['id' => $id, 'deleted' => $deleted !== [], 'deleted_ids' => $deleted];
}

/**
 * Parse the optional `name` / `label` edit inputs shared by the edit abilities.
 *
 * @param array<string, mixed> $input
 * @return array{name:?string, label:?string}|WP_Error
 */
function mo_read_class_edit_fields(array $input, string $kind): array|WP_Error
{
    /** @var mixed $name */
    $name = $input['name'] ?? null;
    /** @var mixed $label */
    $label = $input['label'] ?? null;
    if ($name === null && $label === null) {
        return mo_error('mo_invalid_input', __('Pass at least one of name / label.', domain: 'novamira-pro'));
    }
    if ($name !== null && (!is_string($name) || trim($name) === '')) {
        return mo_error('mo_invalid_input', __('name must be a non-empty string.', domain: 'novamira-pro'));
    }
    if ($label !== null && !is_string($label)) {
        return mo_error('mo_invalid_input', __('label must be a string.', domain: 'novamira-pro'));
    }
    $name = is_string($name) ? trim($name) : null;
    $label = is_string($label) ? trim($label) : null;
    $err = mo_validate_lengths([
        'name' => ['value' => $name ?? '', 'max' => 191],
        'label' => ['value' => $label ?? '', 'max' => 191],
    ]);
    $err ??= $name === null ? null : mo_validate_class_name_input($name, $kind);
    return $err ?? ['name' => $name, 'label' => $label];
}

/**
 * Shared callback body for the three edit abilities.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_edit_class_record(string $kind, string $record_key, string $ability, array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    $fields = mo_read_class_edit_fields($input, $kind);
    if ($fields instanceof WP_Error) {
        return $fields;
    }
    $row = mo_load_class_row($kind, $id);
    if ($row instanceof WP_Error) {
        return $row;
    }
    $theme_id = mo_str($row, key: 'themeID');
    $spec = [
        'kind' => $kind,
        'theme_id' => $theme_id,
        'id' => $id,
        'name' => $fields['name'],
        'label' => $fields['label'],
    ];
    return mo_with_lock("class-write:{$theme_id}", static fn(): array|WP_Error => mo_edit_class_record_locked(
        $spec,
        $record_key,
        $ability,
    ));
}

/**
 * @param array{kind:string, theme_id:string, id:string, name:?string, label:?string} $spec
 * @return array<string, mixed>|WP_Error
 */
function mo_edit_class_record_locked(array $spec, string $record_key, string $ability): array|WP_Error
{
    $written = mo_facade_edit_class($spec);
    if ($written instanceof WP_Error) {
        return $written;
    }
    $record = mo_reload_class_record($spec['kind'], $spec['theme_id'], $spec['id']);
    if ($record instanceof WP_Error) {
        return $record;
    }
    mo_emit_changed(ability: $ability, operation: 'edit', entity: $spec['kind'], entity_id: $spec['id'], context: [
        'theme_id' => $spec['theme_id'],
    ]);
    return [$record_key => $record];
}

/**
 * Shared create body: validate the name, apply Mosaic's label rule, write
 * through the manager, and return the record as stored (Mosaic may have
 * sanitized or deduped the name).
 *
 * @param array{kind:string, theme_id:string, name:string, label:string, parent_type:string, parent_id:string} $spec
 * @return array<string, mixed>|WP_Error
 */
function mo_create_class_record(array $spec, string $record_key, string $ability): array|WP_Error
{
    $err = mo_validate_lengths([
        'name' => ['value' => $spec['name'], 'max' => 191],
        'label' => ['value' => $spec['label'], 'max' => 191],
    ]) ?? mo_validate_class_name_input($spec['name'], $spec['kind']);
    if ($err !== null) {
        return $err;
    }
    if ($spec['label'] === '' && mo_class_name_is_lossy($spec['name'])) {
        // Mosaic keeps the author's text as the label whenever sanitizing it
        // dropped letters or digits (the 1.0.8 migration applies the same rule).
        $spec['label'] = $spec['name'];
    }
    return mo_with_lock("class-write:{$spec['theme_id']}", static fn(): array|WP_Error => mo_create_class_record_locked(
        $spec,
        $record_key,
        $ability,
    ));
}

/**
 * @param array{kind:string, theme_id:string, name:string, label:string, parent_type:string, parent_id:string} $spec
 * @return array<string, mixed>|WP_Error
 */
function mo_create_class_record_locked(array $spec, string $record_key, string $ability): array|WP_Error
{
    $id = mo_facade_create_class($spec);
    if ($id instanceof WP_Error) {
        return $id;
    }
    $record = mo_reload_class_record($spec['kind'], $spec['theme_id'], $id);
    if ($record instanceof WP_Error) {
        return $record;
    }
    mo_emit_changed(ability: $ability, operation: 'create', entity: $spec['kind'], entity_id: $id, context: [
        'theme_id' => $spec['theme_id'],
    ]);
    return [$record_key => $record];
}
