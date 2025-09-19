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
    <title>PPP Loan Search - MySQL Edition</title>
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
            content: "👁️ Click for details";
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

        /* Modal styles */
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
            margin: 2% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            position: relative;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
            word-wrap: break-word;
        }
        .modal-header .loan-amount {
            font-size: 1.8em;
            font-weight: bold;
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
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
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
            flex: 0 0 40%;
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
                width: 95%;
                margin: 5% auto;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PPP Loan Search Interface</h1>
            <p>Lightning-fast search across 968,524 PPP loan records - Click any row for details</p>
            <div class="performance-badge">MySQL Database - Sub-Second Search</div>
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

                                    <!-- Modal for each row -->
                                    <div id="modal<?php echo $index; ?>" class="modal">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h2><?php echo htmlspecialchars($row['BorrowerName'] ?? 'Unknown Company'); ?></h2>
                                                <div class="loan-amount"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></div>
                                                <span class="close" onclick="closeModal(<?php echo $index; ?>)">&times;</span>
                                            </div>
                                            <div class="modal-body">
                                                <div class="detail-grid">
                                                    <div class="detail-section">
                                                        <h3>🏢 Company Information</h3>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Company Name:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['BorrowerName'] ?? '-'); ?></span>
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
                                                            <span class="detail-label">Business Type:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['BusinessType'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Jobs Reported:</span>
                                                            <span class="detail-value"><?php echo number_format($row['JobsReported'] ?? 0); ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="detail-section">
                                                        <h3>💰 Loan Details</h3>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Initial Amount:</span>
                                                            <span class="detail-value currency"><?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Current Amount:</span>
                                                            <span class="detail-value currency"><?php echo formatCurrency($row['CurrentApprovalAmount'] ?? 0); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Forgiveness Amount:</span>
                                                            <span class="detail-value currency"><?php echo formatCurrency($row['ForgivenessAmount'] ?? 0); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Loan Status:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['LoanStatus'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Originating Lender:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['OriginatingLender'] ?? '-'); ?></span>
                                                        </div>
                                                    </div>
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
    </script>
</body>
</html>