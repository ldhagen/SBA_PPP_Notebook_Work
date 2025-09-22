<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test that PHP is working
echo "<!-- PHP is working -->";

$db_config = [
    'host' => '127.0.0.1',
    'port' => '3306', 
    'username' => 'ppp_user',
    'password' => 'ppp_pass_123',
    'database' => 'ppp_loans'
];

function formatCurrency($amount) {
    return $amount ? '$' . number_format((float)$amount, 2) : '$0.00';
}

function formatDate($date) {
    if (!$date || $date === '0000-00-00' || $date === '') return 'N/A';
    return date('M j, Y', strtotime($date));
}

function formatPercentage($value) {
    return $value ? $value . '%' : 'N/A';
}

function sanitizeInput($input) {
    return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
}

// Complete field mappings for comprehensive display
$field_mappings = [
    'LoanNumber' => 'Loan Number', 'DateApproved' => 'Date Approved', 'SBAOfficeCode' => 'SBA Office Code',
    'ProcessingMethod' => 'Processing Method', 'BorrowerName' => 'Company Name', 'BorrowerAddress' => 'Address',
    'BorrowerCity' => 'City', 'BorrowerState' => 'State', 'BorrowerZip' => 'ZIP Code',
    'LoanStatusDate' => 'Loan Status Date', 'LoanStatus' => 'Loan Status', 'Term' => 'Term (months)',
    'SBAGuarantyPercentage' => 'SBA Guaranty %', 'InitialApprovalAmount' => 'Initial Approval Amount',
    'CurrentApprovalAmount' => 'Current Approval Amount', 'UndisbursedAmount' => 'Undisbursed Amount',
    'FranchiseName' => 'Franchise Name', 'ServicingLenderLocationID' => 'Servicing Lender Location ID',
    'ServicingLenderName' => 'Servicing Lender Name', 'ServicingLenderAddress' => 'Servicing Lender Address',
    'ServicingLenderCity' => 'Servicing Lender City', 'ServicingLenderState' => 'Servicing Lender State',
    'ServicingLenderZip' => 'Servicing Lender ZIP', 'RuralUrbanIndicator' => 'Rural/Urban Indicator',
    'HubzoneIndicator' => 'HubZone Indicator', 'LMIIndicator' => 'LMI Indicator',
    'BusinessAgeDescription' => 'Business Age', 'ProjectCity' => 'Project City',
    'ProjectCountyName' => 'Project County', 'ProjectState' => 'Project State', 'ProjectZip' => 'Project ZIP',
    'CD' => 'Congressional District', 'JobsReported' => 'Jobs Reported', 'NAICSCode' => 'NAICS Code',
    'Race' => 'Race', 'Ethnicity' => 'Ethnicity', 'UTILITIES_PROCEED' => 'Utilities Proceeds',
    'PAYROLL_PROCEED' => 'Payroll Proceeds', 'MORTGAGE_INTEREST_PROCEED' => 'Mortgage Interest Proceeds',
    'RENT_PROCEED' => 'Rent Proceeds', 'REFINANCE_EIDL_PROCEED' => 'Refinance EIDL Proceeds',
    'HEALTH_CARE_PROCEED' => 'Health Care Proceeds', 'DEBT_INTEREST_PROCEED' => 'Debt Interest Proceeds',
    'BusinessType' => 'Business Type', 'OriginatingLenderLocationID' => 'Originating Lender Location ID',
    'OriginatingLender' => 'Originating Lender', 'OriginatingLenderCity' => 'Originating Lender City',
    'OriginatingLenderState' => 'Originating Lender State', 'Gender' => 'Gender',
    'Veteran' => 'Veteran Status', 'NonProfit' => 'Non-Profit Status',
    'ForgivenessAmount' => 'Forgiveness Amount', 'ForgivenessDate' => 'Forgiveness Date'
];

// Extended search parameters
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$company_name = isset($_GET['company_name']) ? sanitizeInput($_GET['company_name']) : '';
$city = isset($_GET['city']) ? sanitizeInput($_GET['city']) : '';
$state_filter = isset($_GET['state']) ? sanitizeInput($_GET['state']) : '';
$min_amount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$max_amount = isset($_GET['max_amount']) ? floatval($_GET['max_amount']) : 0;
$loan_number = isset($_GET['loan_number']) ? sanitizeInput($_GET['loan_number']) : '';
$lender_name = isset($_GET['lender_name']) ? sanitizeInput($_GET['lender_name']) : '';
$franchise_name = isset($_GET['franchise_name']) ? sanitizeInput($_GET['franchise_name']) : '';
$naics_code = isset($_GET['naics_code']) ? sanitizeInput($_GET['naics_code']) : '';
$business_type = isset($_GET['business_type']) ? sanitizeInput($_GET['business_type']) : '';
$loan_status = isset($_GET['loan_status']) ? sanitizeInput($_GET['loan_status']) : '';
$jobs_min = isset($_GET['jobs_min']) ? max(0, intval($_GET['jobs_min'])) : 0;
$jobs_max = isset($_GET['jobs_max']) ? intval($_GET['jobs_max']) : 0;
$forgiveness_status = isset($_GET['forgiveness_status']) ? sanitizeInput($_GET['forgiveness_status']) : '';
$race = isset($_GET['race']) ? sanitizeInput($_GET['race']) : '';
$gender = isset($_GET['gender']) ? sanitizeInput($_GET['gender']) : '';
$veteran_status = isset($_GET['veteran_status']) ? sanitizeInput($_GET['veteran_status']) : '';
$zip_code = isset($_GET['zip_code']) ? sanitizeInput($_GET['zip_code']) : '';
$table_source = isset($_GET['table_source']) ? sanitizeInput($_GET['table_source']) : 'ppp_data';
$show_top = isset($_GET['show_top']) ? true : false;
$per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$results = [];
$total_results = 0;
$error_message = '';

$all_states = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'FL' => 'Florida', 'GA' => 'Georgia', 'TX' => 'Texas', 'NY' => 'New York'
];

// Database operations
$should_search = $search_term || $company_name || $city || $state_filter || $min_amount > 0 || $max_amount > 0 || $loan_number || $zip_code || $show_top;

if ($should_search) {
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4",
            $db_config['username'],
            $db_config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $where_conditions = [];
        $params = [];
        
        if ($show_top) {
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
        if ($city) {
            $where_conditions[] = "BorrowerCity LIKE ?";
            $params[] = "%$city%";
        }
        if ($company_name) {
            $where_conditions[] = "BorrowerName LIKE ?";
            $params[] = "%$company_name%";
        }
        if ($loan_number) {
            $where_conditions[] = "LoanNumber LIKE ?";
            $params[] = "%$loan_number%";
        }
        if ($search_term) {
            $where_conditions[] = "(BorrowerName LIKE ? OR BorrowerCity LIKE ?)";
            $params[] = "%$search_term%";
            $params[] = "%$search_term%";
        }
        if ($zip_code) {
            $where_conditions[] = "BorrowerZip LIKE ?";
            $params[] = $zip_code . "%";
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        // Determine which table(s) to search
        if ($table_source === 'both') {
            // Search both tables with UNION - need to list all columns explicitly
            $columns = "LoanNumber, DateApproved, SBAOfficeCode, ProcessingMethod, BorrowerName, BorrowerAddress, BorrowerCity, BorrowerState, BorrowerZip, LoanStatusDate, LoanStatus, Term, SBAGuarantyPercentage, InitialApprovalAmount, CurrentApprovalAmount, UndisbursedAmount, FranchiseName, ServicingLenderLocationID, ServicingLenderName, ServicingLenderAddress, ServicingLenderCity, ServicingLenderState, ServicingLenderZip, RuralUrbanIndicator, HubzoneIndicator, LMIIndicator, BusinessAgeDescription, ProjectCity, ProjectCountyName, ProjectState, ProjectZip, CD, JobsReported, NAICSCode, Race, Ethnicity, UTILITIES_PROCEED, PAYROLL_PROCEED, MORTGAGE_INTEREST_PROCEED, RENT_PROCEED, REFINANCE_EIDL_PROCEED, HEALTH_CARE_PROCEED, DEBT_INTEREST_PROCEED, BusinessType, OriginatingLenderLocationID, OriginatingLender, OriginatingLenderCity, OriginatingLenderState, Gender, Veteran, NonProfit, ForgivenessAmount, ForgivenessDate";
            
            $count_sql = "
                SELECT (
                    (SELECT COUNT(*) FROM loans $where_clause) + 
                    (SELECT COUNT(*) FROM ppp_data $where_clause)
                ) as total
            ";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute(array_merge($params, $params)); // params needed twice for both tables
            $total_results = $count_stmt->fetch()['total'];

            if ($total_results > 0) {
                $offset = ($page - 1) * $per_page;
                $main_sql = "
                    (SELECT 'loans' as source_table, $columns FROM loans $where_clause)
                    UNION ALL
                    (SELECT 'ppp_data' as source_table, $columns FROM ppp_data $where_clause)
                    ORDER BY InitialApprovalAmount DESC
                    LIMIT $offset, $per_page
                ";
                $main_stmt = $pdo->prepare($main_sql);
                $main_stmt->execute(array_merge($params, $params)); // params needed twice for both tables
                $results = $main_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Use single table
            $table_name = ($table_source === 'loans') ? 'loans' : 'ppp_data';
            $count_sql = "SELECT COUNT(*) as total FROM $table_name $where_clause";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_results = $count_stmt->fetch()['total'];

            if ($total_results > 0) {
                $offset = ($page - 1) * $per_page;
                $main_sql = "SELECT * FROM $table_name $where_clause ORDER BY InitialApprovalAmount DESC LIMIT $offset, $per_page";
                $main_stmt = $pdo->prepare($main_sql);
                $main_stmt->execute($params);
                $results = $main_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

$total_pages = $total_results > 0 ? ceil($total_results / $per_page) : 0;

// Determine total record count for header
$total_record_count = $table_source === 'ppp_data' ? '10,499,686' : 
                     ($table_source === 'loans' ? '968,524' : '11,468,210');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PPP Loan Search - Enhanced</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #667eea; color: white; padding: 20px; border-radius: 5px; text-align: center; margin-bottom: 20px; }
        .form { background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px; }
        .btn { padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; margin-right: 10px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .results { background: white; border-radius: 5px; overflow: hidden; }
        .results-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .currency { color: #28a745; font-weight: bold; }
        .clickable { cursor: pointer; }
        .clickable:hover { background: #f0f8ff; }
        .search-tabs { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 20px; }
        .search-tab { 
            padding: 10px 15px; background: #f8f9fa; border: none; cursor: pointer; 
            font-weight: 600; color: #666; border-radius: 5px 5px 0 0; margin-right: 5px; 
        }
        .search-tab.active { background: #667eea; color: white; }
        .search-section { display: none; }
        .search-section.active { display: block; }
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
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 5px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            background: #667eea;
            color: white;
            padding: 15px;
            border-radius: 5px 5px 0 0;
        }
        .modal-body {
            padding: 20px;
        }
        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
        }
        .modal-tab { 
            padding: 8px 12px; background: #f8f9fa; border: none; cursor: pointer; 
            font-weight: 600; color: #666; border-radius: 3px 3px 0 0; margin-right: 3px; 
            font-size: 14px; transition: all 0.3s;
        }
        .modal-tab.active { background: #667eea; color: white; }
        .modal-tab-content { display: none; }
        .modal-tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PPP Loan Enhanced Search</h1>
            <p>Search <?php echo $total_record_count; ?> PPP loan records with advanced options</p>
        </div>

        <div class="form">
            <form method="GET">
                <!-- Search Tabs -->
                <div class="search-tabs">
                    <button type="button" class="search-tab active" onclick="switchTab('basic')">Basic Search</button>
                    <button type="button" class="search-tab" onclick="switchTab('company')">Company Info</button>
                    <button type="button" class="search-tab" onclick="switchTab('loan')">Loan Details</button>
                    <button type="button" class="search-tab" onclick="switchTab('location')">Location</button>
                    <button type="button" class="search-tab" onclick="switchTab('lender')">Lender</button>
                    <button type="button" class="search-tab" onclick="switchTab('demographics')">Demographics</button>
                </div>

                <!-- Basic Search Tab -->
                <div id="basic-search" class="search-section active">
                    <div class="form-row">
                        <div class="form-group">
                            <label>General Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Company, city, lender, franchise">
                        </div>
                        <div class="form-group">
                            <label>Data Source</label>
                            <select name="table_source">
                                <option value="ppp_data" <?php echo $table_source === 'ppp_data' ? 'selected' : ''; ?>>PPP Data (10.5M records)</option>
                                <option value="loans" <?php echo $table_source === 'loans' ? 'selected' : ''; ?>>Loans (968K records)</option>
                                <option value="both" <?php echo $table_source === 'both' ? 'selected' : ''; ?>>Search Both Tables</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <select name="state">
                                <option value="">All States</option>
                                <?php foreach ($all_states as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $state_filter === $code ? 'selected' : ''; ?>><?php echo "$code - $name"; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Minimum Amount ($)</label>
                            <input type="number" name="min_amount" value="<?php echo $min_amount > 0 ? $min_amount : ''; ?>" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Maximum Amount ($)</label>
                            <input type="number" name="max_amount" value="<?php echo $max_amount > 0 ? $max_amount : ''; ?>" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Forgiveness Status</label>
                            <select name="forgiveness_status">
                                <option value="">All Loans</option>
                                <option value="forgiven" <?php echo $forgiveness_status === 'forgiven' ? 'selected' : ''; ?>>Forgiven</option>
                                <option value="not_forgiven" <?php echo $forgiveness_status === 'not_forgiven' ? 'selected' : ''; ?>>Not Forgiven</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Results Per Page</label>
                            <select name="per_page">
                                <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Company Info Tab -->
                <div id="company-search" class="search-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="Specific company name">
                        </div>
                        <div class="form-group">
                            <label>Business Type</label>
                            <input type="text" name="business_type" value="<?php echo htmlspecialchars($business_type); ?>" placeholder="Corporation, LLC, Partnership">
                        </div>
                        <div class="form-group">
                            <label>Franchise Name</label>
                            <input type="text" name="franchise_name" value="<?php echo htmlspecialchars($franchise_name); ?>" placeholder="Franchise name">
                        </div>
                        <div class="form-group">
                            <label>NAICS Code</label>
                            <input type="text" name="naics_code" value="<?php echo htmlspecialchars($naics_code); ?>" placeholder="NAICS code">
                        </div>
                        <div class="form-group">
                            <label>Min Jobs Reported</label>
                            <input type="number" name="jobs_min" value="<?php echo $jobs_min > 0 ? $jobs_min : ''; ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label>Max Jobs Reported</label>
                            <input type="number" name="jobs_max" value="<?php echo $jobs_max > 0 ? $jobs_max : ''; ?>" min="0">
                        </div>
                    </div>
                </div>

                <!-- Loan Details Tab -->
                <div id="loan-search" class="search-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Loan Number</label>
                            <input type="text" name="loan_number" value="<?php echo htmlspecialchars($loan_number); ?>" placeholder="Loan number">
                        </div>
                        <div class="form-group">
                            <label>Loan Status</label>
                            <select name="loan_status">
                                <option value="">All Statuses</option>
                                <option value="Paid in Full" <?php echo $loan_status === 'Paid in Full' ? 'selected' : ''; ?>>Paid in Full</option>
                                <option value="Exemption 4" <?php echo $loan_status === 'Exemption 4' ? 'selected' : ''; ?>>Exemption 4</option>
                                <option value="Active Un-Disbursed" <?php echo $loan_status === 'Active Un-Disbursed' ? 'selected' : ''; ?>>Active Un-Disbursed</option>
                                <option value="Charged Off" <?php echo $loan_status === 'Charged Off' ? 'selected' : ''; ?>>Charged Off</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Location Tab -->
                <div id="location-search" class="search-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="City name">
                        </div>
                        <div class="form-group">
                            <label>ZIP Code</label>
                            <input type="text" name="zip_code" value="<?php echo htmlspecialchars($zip_code); ?>" placeholder="ZIP code">
                        </div>
                    </div>
                </div>

                <!-- Lender Tab -->
                <div id="lender-search" class="search-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Lender Name</label>
                            <input type="text" name="lender_name" value="<?php echo htmlspecialchars($lender_name); ?>" placeholder="Originating or servicing lender">
                        </div>
                    </div>
                </div>

                <!-- Demographics Tab -->
                <div id="demographics-search" class="search-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Race</label>
                            <select name="race">
                                <option value="">All</option>
                                <option value="White" <?php echo $race === 'White' ? 'selected' : ''; ?>>White</option>
                                <option value="Black or African American" <?php echo $race === 'Black or African American' ? 'selected' : ''; ?>>Black or African American</option>
                                <option value="Asian" <?php echo $race === 'Asian' ? 'selected' : ''; ?>>Asian</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">All</option>
                                <option value="Male Owned" <?php echo $gender === 'Male Owned' ? 'selected' : ''; ?>>Male Owned</option>
                                <option value="Female Owned" <?php echo $gender === 'Female Owned' ? 'selected' : ''; ?>>Female Owned</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Veteran Status</label>
                            <select name="veteran_status">
                                <option value="">All</option>
                                <option value="Veteran" <?php echo $veteran_status === 'Veteran' ? 'selected' : ''; ?>>Veteran</option>
                                <option value="Non-Veteran" <?php echo $veteran_status === 'Non-Veteran' ? 'selected' : ''; ?>>Non-Veteran</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Search</button>
                <button type="submit" name="show_top" value="1" class="btn btn-success">Show Top Loans</button>
                <button type="button" class="btn btn-secondary" onclick="clearForm()">Clear All</button>
            </form>
        </div>

        <?php if ($error_message): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($should_search): ?>
            <div class="results">
                <div class="results-header">
                    <strong><?php echo number_format($total_results); ?></strong> loans found
                    <?php if ($total_results > 0): ?>
                        - Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        <?php if ($table_source === 'both'): ?>
                            (searching both tables)
                        <?php else: ?>
                            (<?php echo $table_source === 'ppp_data' ? 'PPP Data table' : 'Loans table'; ?>)
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($total_results > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Loan Amount</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Jobs</th>
                                <th>Status</th>
                                <?php if ($table_source === 'both'): ?>
                                    <th>Source</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $index => $row): ?>
                                <tr class="clickable" onclick="showModal(<?php echo $index; ?>)">
                                    <td><?php echo htmlspecialchars($row['BorrowerName'] ?? 'N/A'); ?></td>
                                    <td class="currency"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($row['BorrowerCity'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['BorrowerState'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($row['JobsReported'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($row['LoanStatus'] ?? 'N/A'); ?></td>
                                    <?php if ($table_source === 'both'): ?>
                                        <td><?php echo htmlspecialchars($row['source_table'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                        <div style="padding: 20px; text-align: center;">
                            <?php
                            $params = $_GET;
                            if ($page > 1) {
                                $params['page'] = $page - 1;
                                echo '<a href="?' . http_build_query($params) . '" style="padding: 8px 12px; margin: 0 3px; text-decoration: none; color: #667eea; border: 1px solid #ddd; border-radius: 3px;">Previous</a>';
                            }
                            
                            for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                                if ($i == $page) {
                                    echo '<span style="padding: 8px 12px; margin: 0 3px; background: #667eea; color: white; border-radius: 3px;">' . $i . '</span>';
                                } else {
                                    $params['page'] = $i;
                                    echo '<a href="?' . http_build_query($params) . '" style="padding: 8px 12px; margin: 0 3px; text-decoration: none; color: #667eea; border: 1px solid #ddd; border-radius: 3px;">' . $i . '</a>';
                                }
                            }
                            
                            if ($page < $total_pages) {
                                $params['page'] = $page + 1;
                                echo '<a href="?' . http_build_query($params) . '" style="padding: 8px 12px; margin: 0 3px; text-decoration: none; color: #667eea; border: 1px solid #ddd; border-radius: 3px;">Next</a>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center; color: #666;">
                        <h3>No results found</h3>
                        <p>Try different search criteria</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Enhanced Modals with Tabs -->
    <?php foreach ($results as $index => $row): ?>
        <div id="modal<?php echo $index; ?>" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" onclick="closeModal(<?php echo $index; ?>)">&times;</span>
                    <h2><?php echo htmlspecialchars($row['BorrowerName'] ?? 'Unknown Company'); ?></h2>
                    <div style="font-size: 1.2em; margin-top: 5px;"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></div>
                    <div style="font-size: 0.9em; opacity: 0.9; margin-top: 5px;">Loan #: <?php echo htmlspecialchars($row['LoanNumber'] ?? 'N/A'); ?></div>
                    <?php if ($table_source === 'both'): ?>
                        <div style="font-size: 0.8em; opacity: 0.8; margin-top: 3px;">Source: <?php echo htmlspecialchars($row['source_table'] ?? 'N/A'); ?> table</div>
                    <?php endif; ?>
                </div>
                <div class="modal-body">
                    <!-- Modal Tabs -->
                    <div style="display: flex; border-bottom: 2px solid #ddd; margin-bottom: 20px; flex-wrap: wrap;">
                        <button class="modal-tab active" onclick="switchModalTab(<?php echo $index; ?>, 'summary')">Summary</button>
                        <button class="modal-tab" onclick="switchModalTab(<?php echo $index; ?>, 'company')">Company</button>
                        <button class="modal-tab" onclick="switchModalTab(<?php echo $index; ?>, 'loan')">Loan Details</button>
                        <button class="modal-tab" onclick="switchModalTab(<?php echo $index; ?>, 'lender')">Lender</button>
                        <button class="modal-tab" onclick="switchModalTab(<?php echo $index; ?>, 'proceeds')">Use of Proceeds</button>
                        <button class="modal-tab" onclick="switchModalTab(<?php echo $index; ?>, 'demographics')">Demographics</button>
                        <button class="modal-tab" onclick="switchModalTab(<?php echo $index; ?>, 'all')">All Fields</button>
                    </div>

                    <!-- Summary Tab -->
                    <div id="summary-<?php echo $index; ?>" class="modal-tab-content active">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h3>Key Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Loan Amount:</span>
                                <span class="currency"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Forgiven Amount:</span>
                                <span class="currency"><?php echo formatCurrency($row['ForgivenessAmount'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Jobs Reported:</span>
                                <span><?php echo number_format($row['JobsReported'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Loan Status:</span>
                                <span><?php echo htmlspecialchars($row['LoanStatus'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Business Type:</span>
                                <span><?php echo htmlspecialchars($row['BusinessType'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Originating Lender:</span>
                                <span><?php echo htmlspecialchars($row['OriginatingLender'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Company Tab -->
                    <div id="company-<?php echo $index; ?>" class="modal-tab-content">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h3>Company Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Company Name:</span>
                                <span><?php echo htmlspecialchars($row['BorrowerName'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Business Type:</span>
                                <span><?php echo htmlspecialchars($row['BusinessType'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Business Age:</span>
                                <span><?php echo htmlspecialchars($row['BusinessAgeDescription'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">NAICS Code:</span>
                                <span><?php echo htmlspecialchars($row['NAICSCode'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Franchise Name:</span>
                                <span><?php echo htmlspecialchars($row['FranchiseName'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address:</span>
                                <span><?php echo htmlspecialchars($row['BorrowerAddress'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">City, State ZIP:</span>
                                <span><?php echo htmlspecialchars(($row['BorrowerCity'] ?? '') . ', ' . ($row['BorrowerState'] ?? '') . ' ' . ($row['BorrowerZip'] ?? '')); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Congressional District:</span>
                                <span><?php echo htmlspecialchars($row['CD'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Loan Details Tab -->
                    <div id="loan-<?php echo $index; ?>" class="modal-tab-content">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h3>Loan Details</h3>
                            <div class="detail-row">
                                <span class="detail-label">Initial Amount:</span>
                                <span class="currency"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Current Amount:</span>
                                <span class="currency"><?php echo formatCurrency($row['CurrentApprovalAmount'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Undisbursed Amount:</span>
                                <span class="currency"><?php echo formatCurrency($row['UndisbursedAmount'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Term (months):</span>
                                <span><?php echo htmlspecialchars($row['Term'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Processing Method:</span>
                                <span><?php echo htmlspecialchars($row['ProcessingMethod'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Lender Tab -->
                    <div id="lender-<?php echo $index; ?>" class="modal-tab-content">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h3>Lender Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Originating Lender:</span>
                                <span><?php echo htmlspecialchars($row['OriginatingLender'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Originating City:</span>
                                <span><?php echo htmlspecialchars($row['OriginatingLenderCity'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Originating State:</span>
                                <span><?php echo htmlspecialchars($row['OriginatingLenderState'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Servicing Lender:</span>
                                <span><?php echo htmlspecialchars($row['ServicingLenderName'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">SBA Office Code:</span>
                                <span><?php echo htmlspecialchars($row['SBAOfficeCode'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Use of Proceeds Tab -->
                    <div id="proceeds-<?php echo $index; ?>" class="modal-tab-content">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h3>Approved Uses of Proceeds</h3>
                            <div class="detail-row">
                                <span class="detail-label">Payroll:</span>
                                <span class="currency"><?php echo formatCurrency($row['PAYROLL_PROCEED'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Utilities:</span>
                                <span class="currency"><?php echo formatCurrency($row['UTILITIES_PROCEED'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Rent:</span>
                                <span class="currency"><?php echo formatCurrency($row['RENT_PROCEED'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Mortgage Interest:</span>
                                <span class="currency"><?php echo formatCurrency($row['MORTGAGE_INTEREST_PROCEED'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Health Care:</span>
                                <span class="currency"><?php echo formatCurrency($row['HEALTH_CARE_PROCEED'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Debt Interest:</span>
                                <span class="currency"><?php echo formatCurrency($row['DEBT_INTEREST_PROCEED'] ?? 0); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Refinance EIDL:</span>
                                <span class="currency"><?php echo formatCurrency($row['REFINANCE_EIDL_PROCEED'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Demographics Tab -->
                    <div id="demographics-<?php echo $index; ?>" class="modal-tab-content">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h3>Demographics & Classifications</h3>
                            <div class="detail-row">
                                <span class="detail-label">Race:</span>
                                <span><?php echo htmlspecialchars($row['Race'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Ethnicity:</span>
                                <span><?php echo htmlspecialchars($row['Ethnicity'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Gender:</span>
                                <span><?php echo htmlspecialchars($row['Gender'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Veteran Status:</span>
                                <span><?php echo htmlspecialchars($row['Veteran'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Non-Profit Status:</span>
                                <span><?php echo htmlspecialchars($row['NonProfit'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">HubZone Indicator:</span>
                                <span><?php echo htmlspecialchars($row['HubzoneIndicator'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">LMI Indicator:</span>
                                <span><?php echo htmlspecialchars($row['LMIIndicator'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Rural/Urban:</span>
                                <span><?php echo htmlspecialchars($row['RuralUrbanIndicator'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- All Fields Tab -->
                    <div id="all-<?php echo $index; ?>" class="modal-tab-content">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h3>Complete Record (All <?php echo count($field_mappings); ?> Fields)</h3>
                            <div style="max-height: 400px; overflow-y: auto; margin-top: 15px;">
                                <?php foreach ($field_mappings as $field => $label): ?>
                                    <div class="detail-row">
                                        <span class="detail-label"><?php echo htmlspecialchars($label); ?>:</span>
                                        <span class="<?php echo (strpos($field, 'Amount') !== false || strpos($field, 'PROCEED') !== false) ? 'currency' : ''; ?>">
                                            <?php 
                                            $value = $row[$field] ?? '';
                                            if (strpos($field, 'Amount') !== false || strpos($field, 'PROCEED') !== false) {
                                                echo formatCurrency($value);
                                            } elseif (strpos($field, 'Date') !== false) {
                                                echo formatDate($value);
                                            } elseif ($field === 'SBAGuarantyPercentage') {
                                                echo formatPercentage($value);
                                            } else {
                                                echo htmlspecialchars($value ?: 'N/A');
                                            }
                                            ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.search-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.search-section').forEach(section => section.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-search').classList.add('active');
        }

        function clearForm() {
            document.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => input.value = '');
            document.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
        }

        function showModal(index) {
            document.getElementById('modal' + index).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(index) {
            document.getElementById('modal' + index).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function switchModalTab(index, tabName) {
            // Hide all tab contents for this modal
            const allTabs = document.querySelectorAll(`#modal${index} .modal-tab-content`);
            allTabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all tab buttons for this modal
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

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (modal.style.display === 'block') {
                        modal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        });

        console.log('Enhanced PPP interface loaded successfully');
    </script>
</body>
</html>