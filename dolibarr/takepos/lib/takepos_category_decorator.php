<?php
/* =============================================================================
 * TakePOS V2 — Category label decoration helper
 * -----------------------------------------------------------------------------
 * Decorates categories with emoji + product count.
 * Shows:  "🥖 Bakery   12"  instead of  "BAKERY -"
 *
 * FIX LOG:
 *  - takeposGetCategoryProductCounts: now accepts an optional $allowedProductIds
 *    parameter (int[] or null). When a branch user is logged in, only products
 *    in their branch's product list are counted. This fixes the bug where branch
 *    category tabs showed the GLOBAL count (e.g. BAKERY 11) instead of the
 *    branch count (e.g. BAKERY 3).
 *  - takeposDecorateCategoryRows: passes the counts map through unchanged;
 *    caller is responsible for passing the branch-filtered counts.
 * ========================================================================== */

if (!function_exists('takeposGetCategoryProductCounts')) {
    /**
     * Get product counts per category in one SQL query.
     *
     * @param   object      $db                 Dolibarr DB handle
     * @param   int         $entity             Current entity id (defaults to 1)
     * @param   array|null  $allowedProductIds  When non-null, only count products
     *                                          whose rowid is in this array.
     *                                          Pass null to count all products (admin/master).
     *                                          Pass [] to count nothing (branch with no products).
     * @return  array<int,int>  Map of category_id => product_count
     */
    function takeposGetCategoryProductCounts($db, $entity = 1, $allowedProductIds = null)
    {
        $entity = (int) $entity;
        if ($entity <= 0) {
            $entity = 1;
        }

        // FIX: if branch has no products at all, every category counts as 0 —
        // return early so the UI shows "0" badges instead of wrong global counts.
        if (is_array($allowedProductIds) && empty($allowedProductIds)) {
            return [];
        }

        $counts = array();
        $sql  = "SELECT cp.fk_categorie AS cat_id, COUNT(DISTINCT cp.fk_product) AS cnt";
        $sql .= " FROM " . MAIN_DB_PREFIX . "categorie_product AS cp";
        $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product AS p ON p.rowid = cp.fk_product";
        $sql .= " WHERE p.entity IN (" . getEntity('product') . ")";
        $sql .= " AND p.tosell = 1";

        // FIX: restrict count to branch-assigned products when branch user is active
        if (is_array($allowedProductIds) && !empty($allowedProductIds)) {
            $safeIds = implode(',', array_map('intval', $allowedProductIds));
            $sql .= " AND p.rowid IN (" . $safeIds . ")";
        }

        $sql .= " GROUP BY cp.fk_categorie";

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $counts[(int) $obj->cat_id] = (int) $obj->cnt;
            }
            $db->free($resql);
        }
        return $counts;
    }
}

if (!function_exists('takeposPickCategoryEmoji')) {
    /**
     * Pick a single emoji for a category based on keywords in its name.
     * Supports both English and Arabic keywords. Returns an empty string if
     * nothing matches.
     *
     * @param   string  $rawLabel  Original (untrimmed) category label
     * @return  string             A single emoji or ''
     */
    function takeposPickCategoryEmoji($rawLabel)
    {
        $label = mb_strtolower((string) $rawLabel, 'UTF-8');

        // If the label already starts with an emoji-like character, don't add one.
        if (preg_match('/^\s*([\x{1F300}-\x{1FAFF}]|[\x{2600}-\x{27BF}])/u', (string) $rawLabel)) {
            return '';
        }

        $rules = array(
            // Bakery / bread
            array(array('bakery','bread','pastry','مخبز','مخبوز','خبز','معجن'), '🥖'),
            // Dairy / milk / cheese
            array(array('dairy','milk','cheese','yogurt','butter','ألبان','حليب','جبن','زبدة','لبن'), '🥛'),
            // Canned / preserved
            array(array('canned','can ','tin','معلب','معلبات','محفوظ'), '🥫'),
            // Drinks - water
            array(array('water','مياه','ماء'), '💧'),
            // Drinks - coffee / tea
            array(array('coffee','tea','شاي','قهوة'), '☕'),
            // Drinks - juice
            array(array('juice','عصير'), '🧃'),
            // Drinks - soda / soft drink
            array(array('soda','soft drink','cola','مشروب','غازي','غازية'), '🥤'),
            // Vegetables / fruits
            array(array('vegetable','veg ','خضار','خضروات','خضراوات'), '🥗'),
            array(array('fruit','فاكهة','فواكه'), '🍎'),
            // Meat / poultry
            array(array('meat','beef','poultry','chicken','لحم','لحوم','دجاج'), '🥩'),
            array(array('fish','seafood','سمك','أسماك','بحر'), '🐟'),
            // Snacks / chips / sweets
            array(array('snack','chip','crisp','شيبس','مقرمشات','وجبات خفيفة'), '🍿'),
            array(array('candy','sweet','chocolate','حلوى','حلويات','شوكولاتة','شوكولاته'), '🍫'),
            // Frozen
            array(array('frozen','ice cream','مجمد','مثلج','آيس'), '🧊'),
            // Grocery (generic)
            array(array('grocery','grocer','بقالة','تموين'), '🛒'),
            // Cleaning / household
            array(array('clean','household','detergent','soap','تنظيف','منزل','منظف','صابون'), '🧴'),
            // Personal care
            array(array('personal care','beauty','cosmet','عناية','تجميل'), '💄'),
            // Baby
            array(array('baby','infant','أطفال','رضع'), '🍼'),
            // Pet
            array(array('pet','حيوان','أليف'), '🐾'),
            // Stationery
            array(array('stationery','office','قرطاسية','مكتب'), '✏️'),
            // Services
            array(array('service','خدمات','خدمة'), '🛎️'),
            // Tobacco
            array(array('tobacco','cigarette','دخان','سجائر','تبغ'), '🚬'),
            // Pharmacy
            array(array('pharma','medic','drug','صيدل','دواء','أدوية'), '💊'),
            // All / everything
            array(array('all','الكل','جميع'), '📦'),
        );

        foreach ($rules as $rule) {
            list($keywords, $emoji) = $rule;
            foreach ($keywords as $kw) {
                if (mb_strpos($label, $kw) !== false) {
                    return $emoji;
                }
            }
        }

        return '📦';
    }
}

if (!function_exists('takeposCleanCategoryName')) {
    /**
     * Clean up category names that have trailing " - " or "—" artifacts.
     */
    function takeposCleanCategoryName($name)
    {
        $name = (string) $name;
        $dashClass = '[-\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]';

        for ($i = 0; $i < 5; $i++) {
            $before = $name;
            $name = preg_replace('/\s*' . $dashClass . '+\s*$/u', '', $name);
            $name = preg_replace('/^\s*' . $dashClass . '+\s*/u', '', $name);
            $name = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]+$/u', '', $name);
            $name = preg_replace('/^[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]+/u', '', $name);
            if ($name === $before) break;
        }

        $name = preg_replace('/\s{2,}/u', ' ', $name);
        return trim($name);
    }
}

if (!function_exists('takeposNormalizeCategoryRows')) {
    /**
     * Normalize category rows before decoration (no-op shim if the function
     * isn't defined elsewhere; index.php calls this before takeposDecorateCategoryRows).
     */
    function takeposNormalizeCategoryRows($rows)
    {
        return is_array($rows) ? $rows : array();
    }
}

if (!function_exists('takeposDecorateCategoryRows')) {
    /**
     * Walk an array of category rows and rewrite their 'label' to show
     * "🥖 Bakery  12".
     *
     * @param   array          $rows    Category rows (from Categorie::get_full_arbo)
     * @param   array<int,int> $counts  category_id => product count map
     *                                  (pass branch-filtered counts for branch users)
     * @return  array  Decorated rows
     */
    function takeposDecorateCategoryRows($rows, $counts)
    {
        if (!is_array($rows)) {
            return array();
        }

        foreach ($rows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $catId = 0;
            if (isset($row['rowid'])) {
                $catId = (int) $row['rowid'];
            } elseif (isset($row['id'])) {
                $catId = (int) $row['id'];
            }

            $rawLabel  = isset($row['label']) ? $row['label'] : '';
            $cleanName = takeposCleanCategoryName($rawLabel);
            $emoji     = takeposPickCategoryEmoji($cleanName);
            // FIX: use 0 when category not in counts map (branch has no products in that category)
            $count     = isset($counts[$catId]) ? (int) $counts[$catId] : 0;

            $newLabel = '';
            if ($emoji !== '') {
                $newLabel .= $emoji . "\xC2\xA0"; // U+00A0 NBSP keeps emoji+name together
            }
            $newLabel .= $cleanName;
            if ($count > 0) {
                $newLabel .= '   ' . $count;
            }

            $row['label_original'] = $rawLabel;
            $row['label']          = $newLabel;

            if (isset($row['description']) && $row['description'] === $rawLabel) {
                $row['description'] = $newLabel;
            }

            $rows[$key] = $row;
        }
        return $rows;
    }
}
