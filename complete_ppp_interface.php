<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_config = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'username' => 'ppp_user',
    'password' => 'ppp_pass_123',
    'database' => 'ppp_loans'
];

$results_per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;

function formatCurrency($amount) {
    return $amount ? '$' . number_format((float)$amount, 2) : '$0.00';
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    if (!$date || $date === '0000-00-00' || $date === '') return 'N/A';
    return date('M j, Y', strtotime($date));
}

function formatPercentage($value) {
    return $value ? $value . '%' : 'N/A';
}

// Define all field mappings for complete display
$field_mappings = [
    'LoanNumber' => 'Loan Number',
    'DateApproved' => 'Date Approved',
    'SBAOfficeCode' => 'SBA Office Code',
    'ProcessingMethod' => 'Processing Method',
    'BorrowerName' => 'Company Name',
    'BorrowerAddress' => 'Address',
    'BorrowerCity' => 'City',
    'BorrowerState' => 'State',
    'BorrowerZip' => 'ZIP Code',
    'LoanStatusDate' => 'Loan Status Date',
    'LoanStatus' => 'Loan Status',
    'Term' => 'Term (months)',
    'SBAGuarantyPercentage' => 'SBA Guaranty %',
    'InitialApprovalAmount' => 'Initial Approval Amount',
    'CurrentApprovalAmount' => 'Current Approval Amount',
    'UndisbursedAmount' => 'Undisbursed Amount',
    'FranchiseName' => 'Franchise Name',
    'ServicingLenderLocationID' => 'Servicing Lender Location ID',
    'ServicingLenderName' => 'Servicing Lender Name',
    'ServicingLenderAddress' => 'Servicing Lender Address',
    'ServicingLenderCity' => 'Servicing Lender City',
    'ServicingLenderState' => 'Servicing Lender State',
    'ServicingLenderZip' => 'Servicing Lender ZIP',
    'RuralUrbanIndicator' => 'Rural/Urban Indicator',
    'HubzoneIndicator' => 'HubZone Indicator',
    'LMIIndicator' => 'LMI Indicator',
    'BusinessAgeDescription' => 'Business Age',
    'ProjectCity' => 'Project City',
    'ProjectCountyName' => 'Project County',
    'ProjectState' => 'Project State',
    'ProjectZip' => 'Project ZIP',
    'CD' => 'Congressional District',
    'JobsReported' => 'Jobs Reported',
    'NAICSCode' => 'NAICS Code',
    'Race' => 'Race',
    'Ethnicity' => 'Ethnicity',
    'UTILITIES_PROCEED' => 'Utilities Proceeds',
    'PAYROLL_PROCEED' => 'Payroll Proceeds',
    'MORTGAGE_INTEREST_PROCEED' => 'Mortgage Interest Proceeds',
    'RENT_PROCEED' => 'Rent Proceeds',
    'REFINANCE_EIDL_PROCEED' => 'Refinance EIDL Proceeds',
    'HEALTH_CARE_PROCEED' => 'Health Care Proceeds',
    'DEBT_INTEREST_PROCEED' => 'Debt Interest Proceeds',
    'BusinessType' => 'Business Type',
    'OriginatingLenderLocationID' => 'Originating Lender Location ID',
    'OriginatingLender' => 'Originating Lender',
    'OriginatingLenderCity' => 'Originating Lender City',
    'OriginatingLenderState' => 'Originating Lender State',
    'Gender' => 'Gender',
    'Veteran' => 'Veteran Status',
    'NonProfit' => 'Non-Profit Status',
    'ForgivenessAmount' => 'Forgiveness Amount',
    'ForgivenessDate' => 'Forgiveness Date'
];

$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sort_column = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'InitialApprovalAmount';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$min_amount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$max_amount = isset($_GET['max_amount']) ? floatval($_GET['max_amount']) : 0;
$state_filter = isset($_GET['state']) ? sanitizeInput($_GET['state']) : '';
$show_top = isset($_GET['show_top']) ? true : false;

$results = [];
$total_results = 0;
$error_message = '';
$processing_time = 0;

$all_states = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
    'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
    'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
    'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
    'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
    'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
    'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
    'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
    'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia'
];

$should_search = $search_term || $min_amount > 0 || $max_amount > 0 || $state_filter || $show_top;

if ($should_search || (!$search_term && !$min_amount && !$max_amount && !$state_filter)) {
    $start_time = microtime(true);
    
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4",
            $db_config['username'],
            $db_config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $where_conditions = [];
        $params = [];
        
        if (!$should_search) {
            $where_conditions[] = "InitialApprovalAmount >= ?";
            $params[] = 1000000;
        }
        
        if ($min_amount > 0) {
            $where_conditions[] = "InitialApprovalAmount >= ?";
            $params[] = $min_amount;
        }
        if ($max_amount > 0) {
            $where_conditions[] = "InitialApprovalAmount <= ?";
            $params[] = $max_amount;
        }
        
        if ($state_filter) {
            $where_conditions[] = "BorrowerState = ?";
            $params[] = $state_filter;
        }
        
        if ($search_term) {
            $where_conditions[] = "(BorrowerName LIKE ? OR BorrowerCity LIKE ?)";
            $params[] = "%$search_term%";
            $params[] = "%$search_term%";
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        $count_sql = "SELECT COUNT(*) as total FROM loans $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_results = $count_stmt->fetch()['total'];

        if ($total_results > 0) {
            $default_order = $sort_column === 'InitialApprovalAmount' ? 'desc' : 'asc';
            $effective_sort_order = isset($_GET['order']) ? $sort_order : $default_order;
            
            $valid_sort_columns = ['InitialApprovalAmount', 'BorrowerName', 'BorrowerCity', 'BorrowerState'];
            if (!in_array($sort_column, $valid_sort_columns)) {
                $sort_column = 'InitialApprovalAmount';
            }
            
            $order_clause = "ORDER BY $sort_column $effective_sort_order";
            $offset = ($page - 1) * $results_per_page;
            $limit_clause = "LIMIT $offset, $results_per_page";
            
            $main_sql = "SELECT * FROM loans $where_clause $order_clause $limit_clause";
            $main_stmt = $pdo->prepare($main_sql);
            $main_stmt->execute($params);
            $results = $main_stmt->fetchAll();
        }

        $processing_time = microtime(true) - $start_time;
        
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

$total_pages = $total_results > 0 ? ceil($total_results / $results_per_page) : 0;
$showing_default = !$should_search && $total_results > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPP Loan Search - Complete Data View</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; color: #333; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; 
            text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .performance-badge { 
            background: rgba(255,255,255,0.2); padding: 8px 20px; 
            border-radius: 25px; display: inline-block; margin-top: 10px; 
        }
        .search-form { 
            background: white; padding: 30px; border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; 
        }
        .form-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; margin-bottom: 20px; 
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 8px; color: #555; }
        .form-group input, .form-group select { 
            padding: 12px; border: 2px solid #e1e5e9; border-radius: 5px; 
            font-size: 14px; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #667eea; outline: none;
        }
        .search-button { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; border: none; padding: 15px 30px; border-radius: 5px; 
            font-size: 16px; font-weight: 600; cursor: pointer; margin-right: 10px;
            transition: transform 0.2s;
        }
        .search-button:hover { transform: translateY(-2px); }
        .show-top-button { 
            background: #28a745; color: white; border: none; padding: 15px 30px; 
            border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; 
        }
        .results-container { 
            background: white; border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden; 
        }
        .results-header { 
            background: #f8f9fa; padding: 20px; border-bottom: 1px solid #e1e5e9; 
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
        }
        .performance-info { 
            background: #e8f5e8; color: #2e7d2e; padding: 8px 16px; 
            border-radius: 5px; font-size: 0.9em; font-weight: 500; 
        }
        .results-table { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 8px; text-align: left; border-bottom: 1px solid #e1e5e9; font-size: 14px; }
        th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
        th a { color: #333; text-decoration: none; }
        th a:hover { color: #667eea; }
        .currency { font-weight: 600; color: #28a745; }
        
        /* Clickable row styles */
        tbody tr {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        tbody tr:hover {
            background: #e3f2fd !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .click-hint {
            position: relative;
        }
        .click-hint::after {
            content: "👁️ Click for complete details";
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            color: #666;
            opacity: 0;
            transition: opacity 0.2s;
        }
        tbody tr:hover .click-hint::after {
            opacity: 1;
        }

        /* Enhanced Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 1% auto;
            padding: 0;
            border-radius: 10px;
            width: 95%;
            max-width: 1200px;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            position: sticky;
            top: 0;
            z-index: 1001;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
            word-wrap: break-word;
            padding-right: 50px;
        }
        .modal-header .loan-amount {
            font-size: 1.8em;
            font-weight: bold;
            margin-top: 5px;
        }
        .modal-header .loan-number {
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: background 0.3s;
        }
        .close:hover {
            background: rgba(255,255,255,0.3);
        }
        .modal-body {
            padding: 30px;
        }
        .modal-tabs {
            display: flex;
            border-bottom: 2px solid #e1e5e9;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .modal-tab {
            padding: 12px 20px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .modal-tab.active {
            background: #667eea;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        .detail-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .detail-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1em;
            border-bottom: 2px solid #e1e5e9;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
            flex: 0 0 45%;
            word-wrap: break-word;
        }
        .detail-value {
            flex: 1;
            text-align: right;
            word-wrap: break-word;
        }
        .detail-value.currency {
            color: #28a745;
            font-weight: 600;
        }
        .detail-value.large-currency {
            color: #28a745;
            font-weight: 700;
            font-size: 1.1em;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e1e5e9;
        }
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
            font-size: 0.9em;
        }
        .export-section {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .export-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            margin: 5px;
            transition: background 0.3s;
        }
        .export-button:hover {
            background: #218838;
        }
        
        .error { 
            background: #f8d7da; color: #721c24; padding: 15px; 
            border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;
        }
        .default-notice { 
            background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; 
            padding: 15px; margin-bottom: 20px; color: #0c5460; 
        }
        .pagination { padding: 20px; text-align: center; }
        .pagination a, .pagination span { 
            display: inline-block; padding: 10px 15px; margin: 0 3px; 
            border-radius: 5px; text-decoration: none; color: #667eea; 
            border: 1px solid #e1e5e9; transition: all 0.2s;
        }
        .pagination a:hover { background: #667eea; color: white; }
        .pagination .current { background: #667eea; color: white; font-weight: 600; }
        .no-results { 
            padding: 60px 20px; text-align: center; color: #6c757d; 
        }
        .no-results h3 { margin-bottom: 10px; }
        @media (max-width: 768px) { 
            .header h1 { font-size: 2em; }
            .form-grid { grid-template-columns: 1fr; }
            .results-header { flex-direction: column; gap: 10px; }
            th, td { padding: 8px 4px; font-size: 12px; }
            .modal-content {
                width: 98%;
                margin: 1% auto;
            }
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
            .detail-value {
                text-align: left;
            }
            .modal-tabs {
                flex-direction: column;
            }
            .modal-tab {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PPP Loan Search Interface</h1>
            <p>Complete data access across 968,524 PPP loan records - Click any row for full details</p>
            <div class="performance-badge">MySQL Database - Complete Field Coverage</div>
        </div>

        <?php if ($showing_default): ?>
        <div class="default-notice">
            <strong>Default View:</strong> Showing loans over $1,000,000. Use the search form below to find specific loans.
        </div>
        <?php endif; ?>

        <form class="search-form" method="GET">
            <div class="form-grid">
                <div class="form-group">
                    <label for="search">Search Term</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Company name or city">
                </div>

                <div class="form-group">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="">All States</option>
                        <?php foreach ($all_states as $code => $name): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $state_filter === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($code . ' - ' . $name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="min_amount">Minimum Amount ($)</label>
                    <input type="number" id="min_amount" name="min_amount" value="<?php echo $min_amount > 0 ? $min_amount : ''; ?>" placeholder="0" step="0.01">
                </div>

                <div class="form-group">
                    <label for="max_amount">Maximum Amount ($)</label>
                    <input type="number" id="max_amount" name="max_amount" value="<?php echo $max_amount > 0 ? $max_amount : ''; ?>" placeholder="No limit" step="0.01">
                </div>

                <div class="form-group">
                    <label for="sort">Sort By</label>
                    <select id="sort" name="sort">
                        <option value="InitialApprovalAmount" <?php echo $sort_column === 'InitialApprovalAmount' ? 'selected' : ''; ?>>Loan Amount</option>
                        <option value="BorrowerName" <?php echo $sort_column === 'BorrowerName' ? 'selected' : ''; ?>>Company Name</option>
                        <option value="BorrowerCity" <?php echo $sort_column === 'BorrowerCity' ? 'selected' : ''; ?>>City</option>
                        <option value="BorrowerState" <?php echo $sort_column === 'BorrowerState' ? 'selected' : ''; ?>>State</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="per_page">Results Per Page</label>
                    <select id="per_page" name="per_page">
                        <option value="10" <?php echo $results_per_page === 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $results_per_page === 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $results_per_page === 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $results_per_page === 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>

            <div style="text-align: center;">
                <button type="submit" class="search-button">Search PPP Loans</button>
                <button type="submit" name="show_top" value="1" class="show-top-button">Show Top Loans</button>
            </div>
        </form>

        <?php if ($error_message): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($results || $total_results === 0): ?>
            <div class="results-container">
                <div class="results-header">
                    <div>
                        <strong><?php echo number_format($total_results); ?></strong> loans found
                        <?php if ($total_results > 0): ?>
                            - Showing <?php echo number_format(($page - 1) * $results_per_page + 1); ?> 
                            to <?php echo number_format(min($page * $results_per_page, $total_results)); ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($processing_time > 0): ?>
                    <div class="performance-info">
                        Query: <?php echo number_format($processing_time * 1000, 0); ?>ms
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_results > 0): ?>
                    <div class="results-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'BorrowerName', 'order' => 'asc', 'page' => 1])); ?>">Company Name</a></th>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'InitialApprovalAmount', 'order' => 'desc', 'page' => 1])); ?>">Loan Amount</a></th>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'BorrowerCity', 'order' => 'asc', 'page' => 1])); ?>">City</a></th>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'BorrowerState', 'order' => 'asc', 'page' => 1])); ?>">State</a></th>
                                    <th>Jobs Reported</th>
                                    <th>Business Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $row): ?>
                                    <tr onclick="showModal(<?php echo $index; ?>)">
                                        <td class="click-hint"><?php echo htmlspecialchars($row['BorrowerName'] ?? 'N/A'); ?></td>
                                        <td class="currency"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars($row['BorrowerCity'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['BorrowerState'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($row['JobsReported'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars($row['BusinessType'] ?? 'N/A'); ?></td>
                                    </tr>

                                    <!-- Enhanced Modal for each row with complete data -->
                                    <div id="modal<?php echo $index; ?>" class="modal">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h2><?php echo htmlspecialchars($row['BorrowerName'] ?? 'Unknown Company'); ?></h2>
                                                <div class="loan-amount"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></div>
                                                <div class="loan-number">Loan #: <?php echo htmlspecialchars($row['LoanNumber'] ?? 'N/A'); ?></div>
                                                <span class="close" onclick="closeModal(<?php echo $index; ?>)">&times;</span>
                                            </div>
                                            <div class="modal-body">
                                                <!-- Export Section -->
                                                <div class="export-section">
                                                    <h4>📊 Export This Record</h4>
                                                    <button class="export-button" onclick="exportToCSV(<?php echo $index; ?>)">Export to CSV</button>
                                                    <button class="export-button" onclick="exportToJSON(<?php echo $index; ?>)">Export to JSON</button>
                                                    <button class="export-button" onclick="copyToClipboard(<?php echo $index; ?>)">Copy All Data</button>
                                                </div>

                                                <!-- Summary Stats -->
                                                <div class="summary-stats">
                                                    <div class="stat-card">
                                                        <div class="stat-value"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></div>
                                                        <div class="stat-label">Initial Amount</div>
                                                    </div>
                                                    <div class="stat-card">
                                                        <div class="stat-value"><?php echo formatCurrency($row['ForgivenessAmount'] ?? 0); ?></div>
                                                        <div class="stat-label">Forgiven Amount</div>
                                                    </div>
                                                    <div class="stat-card">
                                                        <div class="stat-value"><?php echo number_format($row['JobsReported'] ?? 0); ?></div>
                                                        <div class="stat-label">Jobs Reported</div>
                                                    </div>
                                                    <div class="stat-card">
                                                        <div class="stat-value"><?php echo htmlspecialchars($row['LoanStatus'] ?? 'Unknown'); ?></div>
                                                        <div class="stat-label">Loan Status</div>
                                                    </div>
                                                </div>

                                                <!-- Tabbed Content -->
                                                <div class="modal-tabs">
                                                    <button class="modal-tab active" onclick="switchTab(<?php echo $index; ?>, 'company')">🏢 Company Info</button>
                                                    <button class="modal-tab" onclick="switchTab(<?php echo $index; ?>, 'loan')">💰 Loan Details</button>
                                                    <button class="modal-tab" onclick="switchTab(<?php echo $index; ?>, 'proceeds')">📊 Use of Proceeds</button>
                                                    <button class="modal-tab" onclick="switchTab(<?php echo $index; ?>, 'lender')">🏦 Lender Info</button>
                                                    <button class="modal-tab" onclick="switchTab(<?php echo $index; ?>, 'demographics')">👥 Demographics</button>
                                                    <button class="modal-tab" onclick="switchTab(<?php echo $index; ?>, 'all')">📋 All Fields</button>
                                                </div>

                                                <!-- Company Information Tab -->
                                                <div id="company-<?php echo $index; ?>" class="tab-content active">
                                                    <div class="detail-grid">
                                                        <div class="detail-section">
                                                            <h3>🏢 Basic Information</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Company Name:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['BorrowerName'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Business Type:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['BusinessType'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Business Age:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['BusinessAgeDescription'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">NAICS Code:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['NAICSCode'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Jobs Reported:</span>
                                                                <span class="detail-value"><?php echo number_format($row['JobsReported'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Franchise Name:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['FranchiseName'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="detail-section">
                                                            <h3>📍 Location Details</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Address:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['BorrowerAddress'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">City:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['BorrowerCity'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">State:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['BorrowerState'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">ZIP Code:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['BorrowerZip'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Congressional District:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['CD'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Rural/Urban:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['RuralUrbanIndicator'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Loan Details Tab -->
                                                <div id="loan-<?php echo $index; ?>" class="tab-content">
                                                    <div class="detail-grid">
                                                        <div class="detail-section">
                                                            <h3>💰 Loan Information</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Loan Number:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['LoanNumber'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Date Approved:</span>
                                                                <span class="detail-value"><?php echo formatDate($row['DateApproved'] ?? ''); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Initial Amount:</span>
                                                                <span class="detail-value large-currency"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Current Amount:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['CurrentApprovalAmount'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Undisbursed Amount:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['UndisbursedAmount'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Term (months):</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['Term'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="detail-section">
                                                            <h3>📊 Status & Forgiveness</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Loan Status:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['LoanStatus'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Loan Status Date:</span>
                                                                <span class="detail-value"><?php echo formatDate($row['LoanStatusDate'] ?? ''); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Forgiveness Amount:</span>
                                                                <span class="detail-value large-currency"><?php echo formatCurrency($row['ForgivenessAmount'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Forgiveness Date:</span>
                                                                <span class="detail-value"><?php echo formatDate($row['ForgivenessDate'] ?? ''); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">SBA Guaranty %:</span>
                                                                <span class="detail-value"><?php echo formatPercentage($row['SBAGuarantyPercentage'] ?? ''); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Processing Method:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ProcessingMethod'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Use of Proceeds Tab -->
                                                <div id="proceeds-<?php echo $index; ?>" class="tab-content">
                                                    <div class="detail-grid">
                                                        <div class="detail-section">
                                                            <h3>📊 Approved Uses of Proceeds</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Payroll:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['PAYROLL_PROCEED'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Utilities:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['UTILITIES_PROCEED'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Rent:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['RENT_PROCEED'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Mortgage Interest:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['MORTGAGE_INTEREST_PROCEED'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Health Care:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['HEALTH_CARE_PROCEED'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Debt Interest:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['DEBT_INTEREST_PROCEED'] ?? 0); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Refinance EIDL:</span>
                                                                <span class="detail-value currency"><?php echo formatCurrency($row['REFINANCE_EIDL_PROCEED'] ?? 0); ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="detail-section">
                                                            <h3>🏗️ Project Information</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Project City:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ProjectCity'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Project County:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ProjectCountyName'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Project State:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ProjectState'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Project ZIP:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ProjectZip'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">HubZone Indicator:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['HubzoneIndicator'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">LMI Indicator:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['LMIIndicator'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Lender Information Tab -->
                                                <div id="lender-<?php echo $index; ?>" class="tab-content">
                                                    <div class="detail-grid">
                                                        <div class="detail-section">
                                                            <h3>🏦 Originating Lender</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Lender Name:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['OriginatingLender'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Lender City:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['OriginatingLenderCity'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Lender State:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['OriginatingLenderState'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Lender Location ID:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['OriginatingLenderLocationID'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="detail-section">
                                                            <h3>🏦 Servicing Lender</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Servicing Lender:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ServicingLenderName'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Servicing Address:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ServicingLenderAddress'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Servicing City:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ServicingLenderCity'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Servicing State:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ServicingLenderState'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Servicing ZIP:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ServicingLenderZip'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Servicing Location ID:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['ServicingLenderLocationID'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="detail-section">
                                                            <h3>🏛️ SBA Information</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">SBA Office Code:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['SBAOfficeCode'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Demographics Tab -->
                                                <div id="demographics-<?php echo $index; ?>" class="tab-content">
                                                    <div class="detail-grid">
                                                        <div class="detail-section">
                                                            <h3>👥 Demographics & Classifications</h3>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Race:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['Race'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Ethnicity:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['Ethnicity'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Gender:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['Gender'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Veteran Status:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['Veteran'] ?? '-'); ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Non-Profit Status:</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['NonProfit'] ?? '-'); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- All Fields Tab -->
                                                <div id="all-<?php echo $index; ?>" class="tab-content">
                                                    <div class="detail-section">
                                                        <h3>📋 Complete Record (All <?php echo count($field_mappings); ?> Fields)</h3>
                                                        <?php foreach ($field_mappings as $field => $label): ?>
                                                            <div class="detail-row">
                                                                <span class="detail-label"><?php echo htmlspecialchars($label); ?>:</span>
                                                                <span class="detail-value <?php echo (strpos($field, 'Amount') !== false || strpos($field, 'PROCEED') !== false) ? 'currency' : ''; ?>">
                                                                    <?php 
                                                                    $value = $row[$field] ?? '';
                                                                    if (strpos($field, 'Amount') !== false || strpos($field, 'PROCEED') !== false) {
                                                                        echo formatCurrency($value);
                                                                    } elseif (strpos($field, 'Date') !== false) {
                                                                        echo formatDate($value);
                                                                    } elseif ($field === 'SBAGuarantyPercentage') {
                                                                        echo formatPercentage($value);
                                                                    } else {
                                                                        echo htmlspecialchars($value ?: '-');
                                                                    }
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>

                                                <!-- Hidden data for export -->
                                                <div id="export-data-<?php echo $index; ?>" style="display: none;">
                                                    <?php echo htmlspecialchars(json_encode($row)); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php
                            $pagination_params = $_GET;
                            
                            if ($page > 1) {
                                $pagination_params['page'] = $page - 1;
                                echo '<a href="?' . http_build_query($pagination_params) . '">&larr; Previous</a>';
                            }

                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $page) {
                                    echo '<span class="current">' . $i . '</span>';
                                } else {
                                    $pagination_params['page'] = $i;
                                    echo '<a href="?' . http_build_query($pagination_params) . '">' . $i . '</a>';
                                }
                            }

                            if ($page < $total_pages) {
                                $pagination_params['page'] = $page + 1;
                                echo '<a href="?' . http_build_query($pagination_params) . '">Next &rarr;</a>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <h3>No results found</h3>
                        <p>Try adjusting your search criteria or click "Show Top Loans".</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function showModal(index) {
            const modal = document.getElementById('modal' + index);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(index) {
            const modal = document.getElementById('modal' + index);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        function switchTab(index, tabName) {
            // Hide all tab contents
            const allTabs = document.querySelectorAll(`#modal${index} .tab-content`);
            allTabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all tab buttons
            const allButtons = document.querySelectorAll(`#modal${index} .modal-tab`);
            allButtons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab content
            const selectedTab = document.getElementById(`${tabName}-${index}`);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        function exportToCSV(index) {
            const data = JSON.parse(document.getElementById('export-data-' + index).textContent);
            
            // Create CSV content
            const headers = Object.keys(data);
            const values = Object.values(data);
            
            let csvContent = headers.join(',') + '\n';
            csvContent += values.map(value => `"${value || ''}"`).join(',');
            
            // Download file
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ppp_loan_${data.LoanNumber || 'record'}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function exportToJSON(index) {
            const data = JSON.parse(document.getElementById('export-data-' + index).textContent);
            
            const jsonContent = JSON.stringify(data, null, 2);
            
            const blob = new Blob([jsonContent], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ppp_loan_${data.LoanNumber || 'record'}.json`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function copyToClipboard(index) {
            const data = JSON.parse(document.getElementById('export-data-' + index).textContent);
            
            let textContent = `PPP Loan Record - ${data.BorrowerName || 'Unknown'}\n`;
            textContent += '='.repeat(50) + '\n\n';
            
            for (const [key, value] of Object.entries(data)) {
                const label = key.replace(/([A-Z])/g, ' $1').trim();
                textContent += `${label}: ${value || 'N/A'}\n`;
            }
            
            navigator.clipboard.writeText(textContent).then(function() {
                alert('Record data copied to clipboard!');
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = textContent;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Record data copied to clipboard!');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        });

        // Add keyboard navigation for tabs
        document.addEventListener('keydown', function(event) {
            if (event.key >= '1' && event.key <= '6') {
                const openModal = document.querySelector('.modal[style*="block"]');
                if (openModal) {
                    const modalId = openModal.id.replace('modal', '');
                    const tabs = ['company', 'loan', 'proceeds', 'lender', 'demographics', 'all'];
                    const tabIndex = parseInt(event.key) - 1;
                    if (tabIndex < tabs.length) {
                        const tabButton = openModal.querySelector(`.modal-tab:nth-child(${event.key})`);
                        if (tabButton) {
                            tabButton.click();
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>