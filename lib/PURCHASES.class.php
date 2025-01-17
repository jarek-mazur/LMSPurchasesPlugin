<?php

class PURCHASES
{
    private $db;

    public function __construct()
    {
        $this->db = LMSDB::getInstance();
    }

    public function GetPurchaseList($params = array())
    {
        if (!empty($params)) {
            extract($params);
        }

        switch ($orderby) {
            case 'supplierid':
                $orderby = ' ORDER BY pds.supplierid';
                break;
            case 'sdate':
                $orderby = ' ORDER BY pds.sdate';
                break;
            case 'fullnumber':
                $orderby = ' ORDER BY pds.fullnumber';
                break;
            case 'netvalue':
                $orderby = ' ORDER BY pds.netvalue';
                break;
            case 'grossvalue':
                $orderby = ' ORDER BY pds.grossvalue';
                break;
            case 'description':
                $orderby = ' ORDER BY pds.description';
                break;
            case 'id':
            default:
                $orderby = ' ORDER BY pds.id';
                break;
        }

        // SUPPLIER FILTER
        if (!empty($supplier)) {
            $supplierfilter = ' AND supplierid = ' . intval($supplier);
        }

        // PAYMENT FILTER
        if ($payments) {
            switch ($payments) {
                case '-1':
                    $paymentsfilter = ' AND paydate IS NULL';
                    break;
                case '-2':
                    $paymentsfilter = ' AND paydate IS NULL AND (deadline - ?NOW? < 3*86400)';
                    break;
                case '-3':
                    $paymentsfilter = ' AND paydate IS NULL AND (deadline - ?NOW? < 7*86400)';
                    break;
                case '-4':
                    $paymentsfilter = ' AND paydate IS NULL AND (deadline - ?NOW? < 14*86400)';
                    break;
                case '-5':
                    $paymentsfilter = ' AND paydate IS NULL AND (deadline+86399 < ?NOW?)';
                    break;
                case '-6':
                    $paymentsfilter = ' AND paydate IS NULL AND deadline = ' . strtotime("today", time());
                    break;
                case 'all':
                default:
                    $paymentsfilter = '';
                    break;
            }
        }

        // PERIOD FILTER
        if ($period) {
            switch ($period) {
                case '1':
                    $currentweek_firstday = strtotime("monday");
                    $currentweek_lastday = strtotime("monday")+604799;
                    $periodfilter = ' AND sdate BETWEEN ' . $currentweek_firstday . ' AND ' . $currentweek_lastday;
                    break;
                case '2':
                    $previousweek_firstday = strtotime("last week monday");
                    $previousweek_lastday = strtotime("last week sunday")+604799;
                    $periodfilter = ' AND sdate BETWEEN ' . $previousweek_firstday . ' AND ' . $previousweek_lastday;
                    break;
                case '3':
                    $currentmonth_firstday = date_to_timestamp(date('Y/m/01', strtotime("now")));
                    $currentmonth_lastday = date_to_timestamp(date('Y/m/t', strtotime("now")));
                    $periodfilter = ' AND sdate BETWEEN ' . $currentmonth_firstday . ' AND ' . $currentmonth_lastday;
                    break;
                case '4':
                    $previousmonth_firstday = date_to_timestamp(date('Y/m/01', strtotime("last month")));
                    $previousmonth_lastday = date_to_timestamp(date('Y/m/t', strtotime("last month")));
                    $periodfilter = ' AND sdate BETWEEN ' . $previousmonth_firstday . ' AND ' . $previousmonth_lastday;
                    break;
                case '5':
                    $currentmonth = date('n');
                    switch ($currentmonth) {
                        case 1:
                        case 2:
                        case 3:
                            $startq = 1;
                            break;
                        case 4:
                        case 5:
                        case 6:
                            $startq = 4;
                            break;
                        case 7:
                        case 8:
                        case 9:
                            $startq = 7;
                            break;
                        case 10:
                        case 11:
                        case 12:
                            $startq = 10;
                            break;
                        default:
                            break;
                    }
                    $quarter_start = mktime(0, 0, 0, $startq, 1, date('Y'));
                    $quarter_end = mktime(0, 0, 0, $startq + 3, 1, date('Y'))-1;
                    $periodfilter = ' AND sdate BETWEEN ' . $quarter_start . ' AND ' . $quarter_end;
                    break;
                case '6':
                    $currentyear_firstday = date_to_timestamp(date('Y/01/01', strtotime("now")));
                    $currentyear_lastday = date_to_timestamp(date('Y/12/31', strtotime("now")));
                    $periodfilter = ' AND sdate BETWEEN ' . $currentyear_firstday . ' AND ' . $currentyear_lastday;
                    break;
                case 'all':
                default:
                    $periodfilter = '';
                    $paymentsfilter = '';
                    break;
            }
        }

        // VALUE FROM FILTER
        $valuefrom = intval($valuefrom);
        if (!empty($valuefrom)) {
            $valuefromfilter = ' AND grossvalue >= ' . $valuefrom;
        }

        // VALUE TO FILTER
        $valueto = intval($valueto);
        if (!empty($valueto)) {
            $valuetofilter = ' AND grossvalue <= ' . $valueto;
        }

        $result = $this->db->GetAllByKey(
            'SELECT pds.id, pds.typeid, pds.netvalue, pt.name AS typename, pds.fullnumber, pds.taxid, 
                    pds.cdate, pds.sdate, pds.deadline, pds.paytype, pds.paydate, pds.description,
                    pds.supplierid, pds.userid, u.name AS username, tx.value AS tax_value, tx.label AS tax_label,'
                    . $this->db->Concat('cv.lastname', "' '", 'cv.name') . ' AS suppliername
                FROM pds
                    LEFT JOIN customers cv ON (pds.supplierid = cv.id)
                    LEFT JOIN taxes tx ON (pds.taxid = tx.id)
                    LEFT JOIN pdtypes pt ON (pds.typeid = pt.id)
                    LEFT JOIN vusers u ON (pds.userid = u.id)
                    LEFT JOIN pdattachments pda ON (pda.pdid = pds.id)
                WHERE 1=1'
            . $supplierfilter
            . $paymentsfilter
            . $periodfilter
            . $valuefromfilter
            . $valuetofilter
            . $orderby,
            'id'
        );
        foreach ($result as $idx => $val) {
            $result[$idx]['projects'] = $this->GetAssignedProjects($idx);
            $result[$idx]['categories'] = $this->GetAssignedCategories($idx);
            $result[$idx]['files'] = $this->GetPurchaseFiles($idx);
            if (!empty($result[$idx]['tax_value'])) {
                $result[$idx]['grossvalue'] = round($result[$idx]['netvalue']+($result[$idx]['netvalue']*$result[$idx]['tax_value']/100), 2);
            } else {
                $result[$idx]['grossvalue'] = round($result[$idx]['netvalue'], 2);
            }
        }
        return $result;
    }

    public function GetPurchaseFiles($pdid)
    {
        $storage_dir = ConfigHelper::GetConfig("pd.storage_dir", 'storage' . DIRECTORY_SEPARATOR . 'pd');

        return $this->db->GetAll(
            'SELECT filename, contenttype, CONCAT_WS(\'' . DIRECTORY_SEPARATOR . '\', \'' . $storage_dir . '\', ?, filename) AS fullpath
            FROM pdattachments
            WHERE pdid = ?',
            array($pdid, $pdid)
        );
    }

    public function GetAssignedProjects($pdid)
    {
        return $this->db->GetAll(
            'SELECT inv.id AS id, inv.name AS name
                FROM pdprojects AS pdp
                    LEFT JOIN invprojects inv ON (pdp.projectid = inv.id)
                WHERE pdid = ?',
            array($pdid)
        );
    }

    public function SetAssignedProjects($params)
    {
        if (!empty($params['pdid'])) {
            $this->db->Execute(
                'DELETE FROM pdprojects WHERE pdid = ?',
                array($params['pdid'])
            );

            if (!empty($params['invprojects'])) {
                foreach ($params['invprojects'] as $p) {
                    $this->db->Execute(
                        'INSERT INTO pdprojects (pdid, projectid) VALUES (?, ?)',
                        array($params['pdid'], $p)
                    );
                }
            }
        }

        return null;
    }

    public function SetAssignedCategories($params)
    {
        if (!empty($params['pdid'])) {
            $this->db->Execute(
                'DELETE FROM pddoccat WHERE pdid = ?',
                array($params['pdid'])
            );

            if (!empty($params['categories'])) {
                foreach ($params['categories'] as $p) {
                    $this->db->Execute(
                        'INSERT INTO pddoccat (pdid, categoryid) VALUES (?, ?)',
                        array($params['pdid'], $p)
                    );
                }
            }
        }

        return null;
    }

    public function GetAssignedCategories($pdid)
    {
        return $this->db->GetAll(
            'SELECT pdc.id, name
                FROM pddoccat AS pdc
                    LEFT JOIN pdcategories pc ON (pc.id = pdc.categoryid)
                WHERE pdid = ?',
            array($pdid)
        );
    }

    public function GetPurchaseInfo($id)
    {
        $result = $this->db->GetRow(
            'SELECT pds.id, pds.typeid, pds.fullnumber, pds.netvalue, pds.taxid, pds.grossvalue, pds.cdate, 
            pds.sdate, pds.deadline, pds.paytype, pds.paydate, pds.description, tx.value AS tax_value, tx.label AS tax_label
            pds.supplierid, ' . $this->db->Concat('cv.lastname', "' '", 'cv.name') . ' AS suppliername
            FROM pds
                LEFT JOIN customers cv ON (pds.supplierid = cv.id)
                LEFT JOIN taxes tx ON (pds.taxid = tx.id)
            WHERE pds.id = ?',
            array($id)
        );

        if ($result) {
            $result['projects'] = $this->GetAssignedProjects($id);
            $result['categories'] = $this->GetAssignedCategories($id);
        }

        return $result;
    }

    private function AddPurchaseFiles($pdid, $files, $cleanup = false)
    {
        $pd_dir = ConfigHelper::getConfig('pd.storage_dir', STORAGE_DIR . DIRECTORY_SEPARATOR . 'pd');
        $storage_dir_permission = intval(ConfigHelper::getConfig('storage.dir_permission', '0700'), 8);
        $storage_dir_owneruid = ConfigHelper::getConfig('storage.dir_owneruid', 'www-data');
        $storage_dir_ownergid = ConfigHelper::getConfig('storage.dir_ownergid', 'www-data');

        if (!empty($files) && $pd_dir) {
            $pdid_dir = $pd_dir . DIRECTORY_SEPARATOR . $pdid;

            @umask(0007);

            @mkdir($pdid_dir, $storage_dir_permission);
            @chown($pdid_dir, $storage_dir_owneruid);
            @chgrp($pdid_dir, $storage_dir_ownergid);

            $dirs_to_be_deleted = array();
            foreach ($files as $file) {
                $filename = preg_replace('/[^\w\.-_]/', '_', basename($file['name']));
                $dstfile = $pdid_dir . DIRECTORY_SEPARATOR . $filename;
                if (isset($file['content'])) {
                    $fh = @fopen($dstfile, 'w');
                    if (empty($fh)) {
                        continue;
                    }
                    fwrite($fh, $file['content'], strlen($file['content']));
                    fclose($fh);
                } else {
                    if ($cleanup) {
                        $dirs_to_be_deleted[] = dirname($file['name']);
                    }
                    if (!@rename(isset($file['tmp_name']) ? $file['tmp_name'] : $file['name'], $dstfile)) {
                        continue;
                    }
                }

                @chown($dstfile, $storage_dir_owneruid);
                @chgrp($dstfile, $storage_dir_ownergid);

                $this->db->Execute(
                    'INSERT INTO pdattachments (pdid, filename, contenttype) VALUES (?, ?, ?)',
                    array($pdid, $filename, $file['type'])
                );
            }
            if (!empty($dirs_to_be_deleted)) {
                $dirs_to_be_deleted = array_unique($dirs_to_be_deleted);
                foreach ($dirs_to_be_deleted as $dir) {
                    rrmdir($dir);
                }
            }
        }
    }

    public function AddPurchase($args, $files = null)
    {
        $invprojects = empty($args['invprojects']) ? null : $args['invprojects'];
        $categories = empty($args['categories']) ? null : $args['categories'];

        $args = array(
            'typeid' => empty($args['typeid']) ? null : $args['typeid'],
            'fullnumber' => $args['fullnumber'],
            'netvalue' => str_replace(",", ".", $args['netvalue']),
            'taxid' => $args['taxid'],
            'sdate' => empty($args['sdate']) ? null : date_to_timestamp($args['sdate']),
            'deadline' => empty($args['deadline']) ? null : date_to_timestamp($args['deadline']),
            'paytype' => empty($args['paytype']) ? ConfigHelper::getConfig('pd.default_paytype', 2) : $args['paytype'],
            'paydate' => empty($args['paydate']) ? null : date_to_timestamp($args['paydate']),
            'description' => empty($args['description']) ? null : $args['description'],
            'supplierid' => $args['supplierid'],
            'userid' => Auth::GetCurrentUser(),
        );

        $result = $this->db->Execute(
            'INSERT INTO pds (typeid, fullnumber, netvalue, taxid, cdate, sdate, deadline, paytype, paydate, description, supplierid, userid)
                    VALUES (?, ?, ?, ?, ?NOW?, ?, ?, ?, ?, ?, ?, ?)',
            $args
        );

        $params['pdid'] = $this->db->GetLastInsertID('pds');

        if (!empty($invprojects)) {
            $params['invprojects'] = $invprojects;
            $this->SetAssignedProjects($params);
        }

        if (!empty($categories)) {
            $params['categories'] = $categories;
            $this->SetAssignedCategories($params);
        }

        if (!empty($files)) {
            $this->AddPurchaseFiles($params['pdid'], $files);
        }

        return $result;
    }

    public function DeletePurchaseDocument($id)
    {
        return $this->db->Execute('DELETE FROM pds WHERE id = ?', array($id));
    }

    public function MarkAsPaid($id)
    {
        return $this->db->Execute('UPDATE pds SET paydate = ?NOW? WHERE id = ?', array($id));
    }

    public function UpdatePurchaseDocument($args)
    {
        ///porównać to co jest aktualnie w projekcie i to co wybieramy i warunkowo odpalić ten kod -
        $params['pdid'] = $args['id'];
        $params['invprojects'] = !empty($args['invprojects']) ? $args['invprojects'] : null;
        $this->SetAssignedProjects($params);
        $params['categories'] = !empty($args['categories']) ? $args['categories'] : null;
        $this->SetAssignedCategories($params);
        ///

        $args = array(
            'typeid' => empty($args['typeid']) ? null : $args['typeid'],
            'fullnumber' => $args['fullnumber'],
            'netvalue' => str_replace(",", ".", $args['netvalue']),
            'taxid' => $args['taxid'],
            'sdate' => empty($args['sdate']) ? null : date_to_timestamp($args['sdate']),
            'deadline' => empty($args['deadline']) ? null : date_to_timestamp($args['deadline']),
            'paytype' => empty($args['paytype']) ? ConfigHelper::getConfig('pd.default_paytype', 2) : $args['paytype'],
            'paydate' => empty($args['paydate']) ? null : date_to_timestamp($args['paydate']),
            'description' => empty($args['description']) ? null : $args['description'],
            'supplierid' => $args['supplierid'],
            'id' => $args['id'],
        );

        $result = $this->db->Execute(
            'UPDATE pds SET typeid = ?, fullnumber = ?, netvalue = ?, taxid = ?, sdate = ?, deadline = ?, paytype = ?,
                    paydate = ? , description = ?, supplierid = ? WHERE id = ?',
            $args
        );

        return $result;
    }

    public function GetSuppliers()
    {
        return $this->db->GetAllByKey(
            'SELECT *
            FROM customerview
            WHERE (flags & ? = ?)',
            'id',
            array(
                CUSTOMER_FLAG_SUPPLIER,
                CUSTOMER_FLAG_SUPPLIER
            )
        );
    }

    public function GetPurchaseDocumentTypesList($params = array())
    {
        if (!empty($params)) {
            extract($params);
        }

        switch ($orderby) {
            case 'name':
                $orderby = ' ORDER BY pdtypes.name';
                break;
            case 'description':
                $orderby = ' ORDER BY pdtypes.description';
                break;
            case 'id':
            default:
                $orderby = ' ORDER BY pdtypes.id';
                break;
        }

        return $this->db->GetAllByKey(
            'SELECT pdtypes.id, pdtypes.name, pdtypes.description
                FROM pdtypes '
            . $orderby,
            'id'
        );
    }

    public function GetPurchaseTypeInfo($id)
    {
        $result = $this->db->GetAll(
            'SELECT pdtypes.id, pdtypes.name, pdtypes.description
            FROM pdtypes
            WHERE pdtypes.id = ?',
            array($id)
        );

        return $result;
    }

    public function AddPurchaseType($args)
    {
        $args = array(
            'name' => $args['name'],
            'description' => empty($args['description']) ? null : $args['description']
        );

        $result = $this->db->Execute(
            'INSERT INTO pdtypes (name, description) VALUES (?, ?)',
            $args
        );

        return $result;
    }

    public function DeletePurchaseDocumentType($id)
    {
        return $this->db->Execute('DELETE FROM pdtypes WHERE id = ?', array($id));
    }

    public function UpdatePurchaseDocumentType($args)
    {
        $args = array(
            'name' => $args['name'],
            'description' => empty($args['description']) ? null : $args['description'],
            'id' => $args['id'],
        );

        $result = $this->db->Execute(
            'UPDATE pdtypes SET name = ?, description = ? WHERE id = ?',
            $args
        );

        return $result;
    }

    public function GetPurchaseCategoryList($params = array())
    {
        if (!empty($params)) {
            extract($params);
        }

        switch ($orderby) {
            case 'name':
                $orderby = ' ORDER BY pdcategories.name';
                break;
            case 'description':
                $orderby = ' ORDER BY pdcategories.description';
                break;
            case 'id':
            default:
                $orderby = ' ORDER BY pdcategories.id';
                break;
        }

        return $this->db->GetAllByKey(
            'SELECT pdcategories.id, pdcategories.name, pdcategories.description
                FROM pdcategories '
            . $orderby,
            'id'
        );
    }

    public function GetPurchaseCategoryInfo($id)
    {
        $result = $this->db->GetAll(
            'SELECT pdcategories.id, pdcategories.name, pdcategories.description
            FROM pdcategories
            WHERE pdcategories.id = ?',
            array($id)
        );

        return $result;
    }

    public function AddPurchaseCategory($args)
    {
        $args = array(
            'name' => $args['name'],
            'description' => empty($args['description']) ? null : $args['description']
        );

        $result = $this->db->Execute(
            'INSERT INTO pdcategories (name, description) VALUES (?, ?)',
            $args
        );

        return $result;
    }

    public function DeletePurchaseCategory($id)
    {
        return $this->db->Execute('DELETE FROM pdcategories WHERE id = ?', array($id));
    }

    public function UpdatePurchaseCategory($args)
    {
        $args = array(
            'name' => $args['name'],
            'description' => empty($args['description']) ? null : $args['description'],
            'id' => $args['id'],
        );

        $result = $this->db->Execute(
            'UPDATE pdcategories SET name = ?, description = ? WHERE id = ?',
            $args
        );

        return $result;
    }

    public function PDStats()
    {
        define('PD_PAID', 0);
        define('PD_OVERDUE', 1);
        define('PD_TODAY', 2);
        define('PD_TOMORROW', 3);
        define('PD_IN3DAYS', 4);
        define('PD_IN7DAYS', 5);
        define('PD_IN14DAYS', 6);


        $PDSTATS = array(
            PD_PAID => array(
                'summarylabel' => trans('Paid'),
                'filter' => 'paydate IS NOT NULL',
                'alias' => 'paid'
            ),
            PD_OVERDUE => array(
                'summarylabel' => trans('Overdue'),
                'filter' => 'paydate IS NULL AND (deadline+86399 < ?NOW?)',
                'alias' => 'overdue'
            ),
            PD_TODAY => array(
                'summarylabel' => trans('Today'),
                'filter' => 'paydate IS NULL AND (deadline+86399 > ?NOW?) AND (deadline - ?NOW? < 86399)',
                'alias' => 'today'
            ),
            PD_TOMORROW => array(
                'summarylabel' => trans('Tomorrow'),
                'filter' => 'paydate IS NULL AND (deadline+2*86399 > ?NOW?) AND (deadline - ?NOW? < 2*86399)',
                'alias' => 'tomorrow'
            ),
            PD_IN3DAYS => array(
                'summarylabel' => trans('In 3 days:'),
                'filter' => 'paydate IS NULL AND deadline - ?NOW? < 3*86400',
                'alias' => 'in3days'
            ),
            PD_IN7DAYS => array(
                'summarylabel' => trans('In 7 days:'),
                'filter' => 'paydate IS NULL AND deadline - ?NOW? < 7*86400',
                'alias' => 'in7days'
            ),
            PD_IN14DAYS => array(
                'summarylabel' => trans('In 14 days:'),
                'filter' => 'paydate IS NULL AND deadline - ?NOW? < 14*86400',
                'alias' => 'in14days'
            ),
        );

        $sql = '';
        foreach ($PDSTATS as $statusidx => $status) {
            $sql .= ' COUNT(CASE WHEN ' . $status['filter'] . ' THEN 1 END) AS ' . $status['alias'] . ',
            SUM(CASE WHEN ' . $status['filter'] . ' THEN grossvalue END) AS '.$status['alias'].'value,
            ';
        }
        $result = $this->db->GetRow(
            'SELECT
            ' . $sql . ' COUNT(id) AS unpaid
            FROM pds
            '
        );
        return $result;
    }

    public function SupplierStats()
    {
        global $CSTATUSES;
        $sql = '';
        foreach ($CSTATUSES as $statusidx => $status) {
            $sql .= ' COUNT(CASE WHEN status = ' . $statusidx . ' THEN 1 END) AS ' . $status['alias'] . ',';
        }
        $result = $this->db->GetRow(
            'SELECT ' . $sql . ' COUNT(id) AS total
            FROM customerview
            WHERE deleted=0'
        );

        $tmp = $this->db->GetRow(
            'SELECT
                SUM(a.value) * -1 AS debtvalue,
                COUNT(*) AS debt,
                SUM(CASE WHEN a.status = ? THEN a.value ELSE 0 END) * -1 AS debtcollectionvalue
            FROM (
                SELECT c.status, b.balance AS value
                FROM customerbalances b
                LEFT JOIN customerview c ON (customerid = c.id)
                WHERE c.deleted = 0 AND b.balance < 0
            ) a',
            array(
                CSTATUS_DEBT_COLLECTION,
            )
        );

        if (is_array($tmp)) {
            $result = array_merge($result, $tmp);
        }

        return $result;
    }

// bazuje na https://github.com/kyob/LMSIncomePlugin
    public function SalePerMonth($only_year)
    {
        $income = $this->db->GetAll(
            'SELECT EXTRACT(MONTH FROM to_timestamp(time)) AS month, SUM(value)* (-1) AS suma
                   FROM cash
                   WHERE value<0
                     AND EXTRACT(YEAR FROM to_timestamp(time))=' . $only_year . '
                   GROUP BY EXTRACT(MONTH FROM to_timestamp(time))
                   ORDER BY month
        '
        );
        return $income;
    }

    public function SalePerMonthType($only_year,$servicetype = 'all')
    {
            switch ($servicetype) {
                case '-1':
                $inv = ' AND servicetype=-1 ';
                break;
                case '1':
                $inv = ' AND servicetype=1 ';
                break;
                case '2':
                $inv = ' AND servicetype=2 ';
                break;
                case '3':
                $inv = ' AND servicetype=3 ';
                break;
                case '4':
                $inv = ' AND servicetype=4 ';
                break;
                case '5':
                $inv = ' AND servicetype=5 ';
                break;
                case '6':
                $inv = ' AND servicetype=6 ';
            case 'all':
            default:
                break;
        }
        $income = $this->db->GetAll(
            'SELECT EXTRACT(MONTH FROM to_timestamp(time)) AS month, SUM(value)* (-1) AS suma
                   FROM cash
                   WHERE value<0
                     AND EXTRACT(YEAR FROM to_timestamp(time))=' . $only_year . '
                     ' . $inv . '
                   GROUP BY EXTRACT(MONTH FROM to_timestamp(time))
                   ORDER BY month
        '
        );
        return $income;
    }

    // bazuje na https://github.com/kyob/LMSIncomePlugin
    public function IncomePerMonth($only_year)
    {
        $income = $this->db->GetAll(
            'SELECT EXTRACT(MONTH FROM to_timestamp(time)) AS month, SUM(value) AS suma
                   FROM cash
                   WHERE importid IS NOT NULL
                     AND value>0
                     AND EXTRACT(YEAR FROM to_timestamp(time))=' . $only_year . '
                   GROUP BY EXTRACT(MONTH FROM to_timestamp(time))
                   ORDER BY month
        '
        );
        return $income;
    }
}
