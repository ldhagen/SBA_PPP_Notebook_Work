<?php
// PPP Loan Search Interface - Simple working version with inline modals
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$csv_file = './sba_csv/public_150k_plus_240930.csv';
$results_per_page = 25;
$max_memory = '512M';
ini_set('memory_limit', $max_memory);
set_time_limit(300);

// Helper functions
function formatCurrency($amount) {
    try {
        return '$' . number_format((float)$amount, 2);
    } catch (Exception $e) {
        return '$0.00';
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

// Process search parameters
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$search_column = isset($_GET['column']) ? sanitizeInput($_GET['column']) : '';
$sort_column = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'InitialApprovalAmount';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$min_amount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$max_amount = isset($_GET['max_amount']) ? floatval($_GET['max_amount']) : 0;
$state_filter = isset($_GET['state']) ? sanitizeInput($_GET['state']) : '';
$show_top = isset($_GET['show_top']) ? true : false;

// Check if file exists
if (!file_exists($csv_file)) {
    die("CSV file not found: $csv_file");
}

$results = [];
$total_results = 0;
$error_message = '';
$processing_time = 0;

// Get available columns and sample states
$available_columns = [];
$sample_states = [];

try {
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        $headers = fgetcsv($handle, 0, ',');
        if ($headers) {
            $available_columns = $headers;
            
            // Get sample states (first 200 rows only for speed)
            $state_index = array_search('BorrowerState', $headers);
            if ($state_index !== false) {
                $states_found = [];
                $sample_count = 0;
                while (($row = fgetcsv($handle, 0, ',')) !== FALSE && $sample_count < 200) {
                    if (isset($row[$state_index]) && $row[$state_index]) {
                        $state = trim($row[$state_index]);
                        if ($state && !isset($states_found[$state])) {
                            $states_found[$state] = true;
                            $sample_states[] = $state;
                        }
                    }
                    $sample_count++;
                }
                sort($sample_states);
            }
        }
        fclose($handle);
    }
} catch (Exception $e) {
    $available_columns = ['BorrowerName', 'InitialApprovalAmount', 'BorrowerCity', 'BorrowerState', 'LoanStatus', 'DateApproved', 'ForgivenessAmount'];
}

// Determine if we should process search
$should_search = $search_term || $min_amount > 0 || $max_amount > 0 || $state_filter || $show_top;

// Process search if criteria provided OR show top loans by default
if ($should_search || (!$search_term && !$min_amount && !$max_amount && !$state_filter)) {
    $start_time = microtime(true);
    
    try {
        $handle = fopen($csv_file, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }
        
        // Read header row
        $headers = fgetcsv($handle, 0, ',');
        if (!$headers) {
            throw new Exception("Cannot read CSV headers");
        }
        
        // Find column indices
        $column_indices = array_flip($headers);
        $amount_index = isset($column_indices['InitialApprovalAmount']) ? $column_indices['InitialApprovalAmount'] : -1;
        $name_index = isset($column_indices['BorrowerName']) ? $column_indices['BorrowerName'] : -1;
        $city_index = isset($column_indices['BorrowerCity']) ? $column_indices['BorrowerCity'] : -1;
        $state_index = isset($column_indices['BorrowerState']) ? $column_indices['BorrowerState'] : -1;
        $status_index = isset($column_indices['LoanStatus']) ? $column_indices['LoanStatus'] : -1;
        
        $search_index = $search_column && isset($column_indices[$search_column]) ? 
                       $column_indices[$search_column] : -1;
        
        $temp_results = [];
        $row_count = 0;
        $max_search_results = 2000; // Limit for performance
        
        // If no search criteria, show top loans (high amounts)
        $default_min_amount = 0;
        if (!$should_search) {
            $default_min_amount = 1000000; // Show loans over $1M by default
        }
        
        // Process each row
        while (($row = fgetcsv($handle, 0, ',')) !== FALSE && count($temp_results) < $max_search_results) {
            $row_count++;
            
            // Skip if row doesn't have enough columns
            if (count($row) < count($headers)) {
                continue;
            }
            
            $loan_amount = $amount_index >= 0 ? 
                          floatval(str_replace(['$', ','], '', $row[$amount_index] ?? '0')) : 0;
            
            // Apply filters
            $matches = true;
            
            // Default filter for high-value loans when no search criteria
            if (!$should_search && $loan_amount < $default_min_amount) {
                $matches = false;
            }
            
            // Amount filters
            if ($min_amount > 0 && $loan_amount < $min_amount) {
                $matches = false;
            }
            if ($max_amount > 0 && $loan_amount > $max_amount) {
                $matches = false;
            }
            
            // State filter
            if ($state_filter && $state_index >= 0) {
                if (stripos($row[$state_index] ?? '', $state_filter) === false) {
                    $matches = false;
                }
            }
            
            // Search term filter
            if ($search_term && $matches) {
                $search_matches = false;
                
                if ($search_index >= 0) {
                    // Search in specific column
                    if (stripos($row[$search_index] ?? '', $search_term) !== false) {
                        $search_matches = true;
                    }
                } else {
                    // Search in multiple key columns
                    $search_columns = [$name_index, $city_index, $state_index];
                    foreach ($search_columns as $idx) {
                        if ($idx >= 0 && stripos($row[$idx] ?? '', $search_term) !== false) {
                            $search_matches = true;
                            break;
                        }
                    }
                }
                
                if (!$search_matches) {
                    $matches = false;
                }
            }
            
            if ($matches) {
                // Create result row
                $result_row = [];
                foreach ($headers as $i => $header) {
                    $result_row[$header] = isset($row[$i]) ? $row[$i] : '';
                }
                $result_row['_loan_amount_numeric'] = $loan_amount;
                $temp_results[] = $result_row;
            }
        }
        
        fclose($handle);
        
        $total_results = count($temp_results);
        
        // Sort results (default: highest loans first)
        if ($total_results > 0) {
            $sort_key = $sort_column === 'InitialApprovalAmount' ? '_loan_amount_numeric' : $sort_column;
            
            usort($temp_results, function($a, $b) use ($sort_key, $sort_order) {
                $val_a = isset($a[$sort_key]) ? $a[$sort_key] : '';
                $val_b = isset($b[$sort_key]) ? $b[$sort_key] : '';
                
                if (is_numeric($val_a) && is_numeric($val_b)) {
                    $result = $val_a <=> $val_b;
                } else {
                    $result = strcasecmp($val_a, $val_b);
                }
                
                return $sort_order === 'asc' ? $result : -$result;
            });
            
            // Paginate results
            $start_index = ($page - 1) * $results_per_page;
            $results = array_slice($temp_results, $start_index, $results_per_page);
        }
        
        $processing_time = microtime(true) - $start_time;
        
    } catch (Exception $e) {
        $error_message = "Error processing file: " . $e->getMessage();
    }
}

// Calculate pagination
$total_pages = $total_results > 0 ? ceil($total_results / $results_per_page) : 0;

// Determine what we're showing
$showing_default = !$should_search && $total_results > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPP Loan Search Interface</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .search-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }

        .search-button:hover {
            transform: translateY(-2px);
        }

        .show-top-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .results-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .results-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e1e5e9;
        }

        .results-table {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }

        th a {
            color: #333;
            text-decoration: none;
        }

        th a:hover {
            color: #667eea;
        }

        .currency {
            font-weight: 600;
            color: #28a745;
        }

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
            margin-bottom: 20px;
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
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .default-notice {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            color: #0c5460;
        }

        .pagination {
            padding: 20px;
            text-align: center;
        }

        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
            border: 1px solid #e1e5e9;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .current {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .header h1 { font-size: 2em; }
            .form-grid { grid-template-columns: 1fr; }
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
            <p>Search and analyze PPP loan data - Click any row for details</p>
        </div>

        <?php if ($showing_default): ?>
        <div class="default-notice">
            <strong>Default View:</strong> Showing loans over $1,000,000. Use the search form below to find specific loans or adjust filters.
        </div>
        <?php endif; ?>

        <form class="search-form" method="GET">
            <div class="form-grid">
                <div class="form-group">
                    <label for="search">Search Term</label>
                    <input type="text" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="Enter company name, city, etc.">
                </div>

                <div class="form-group">
                    <label for="column">Search In Column</label>
                    <select id="column" name="column">
                        <option value="">All Key Columns</option>
                        <?php foreach (['BorrowerName', 'BorrowerCity', 'BorrowerState', 'LoanStatus'] as $col): ?>
                            <?php if (in_array($col, $available_columns)): ?>
                                <option value="<?php echo htmlspecialchars($col); ?>" 
                                        <?php echo $search_column === $col ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($col); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="">All States</option>
                        <?php foreach (array_slice($sample_states, 0, 20) as $state): ?>
                            <option value="<?php echo htmlspecialchars($state); ?>" 
                                    <?php echo $state_filter === $state ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($state); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="min_amount">Minimum Amount ($)</label>
                    <input type="number" id="min_amount" name="min_amount" 
                           value="<?php echo $min_amount > 0 ? $min_amount : ''; ?>" 
                           placeholder="0" step="0.01">
                </div>

                <div class="form-group">
                    <label for="max_amount">Maximum Amount ($)</label>
                    <input type="number" id="max_amount" name="max_amount" 
                           value="<?php echo $max_amount > 0 ? $max_amount : ''; ?>" 
                           placeholder="No limit" step="0.01">
                </div>

                <div class="form-group">
                    <label for="sort">Sort By</label>
                    <select id="sort" name="sort">
                        <option value="InitialApprovalAmount" <?php echo $sort_column === 'InitialApprovalAmount' ? 'selected' : ''; ?>>Loan Amount</option>
                        <option value="BorrowerName" <?php echo $sort_column === 'BorrowerName' ? 'selected' : ''; ?>>Company Name</option>
                        <option value="BorrowerState" <?php echo $sort_column === 'BorrowerState' ? 'selected' : ''; ?>>State</option>
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
                        <?php if ($processing_time > 0): ?>
                            (processed in <?php echo number_format($processing_time, 2); ?>s)
                        <?php endif; ?>
                        
                        <?php if ($total_results > 0): ?>
                            - Showing <?php echo number_format(($page - 1) * $results_per_page + 1); ?> 
                            to <?php echo number_format(min($page * $results_per_page, $total_results)); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($total_results > 0): ?>
                    <div class="results-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'BorrowerName', 'order' => 'asc', 'page' => 1])); ?>">
                                            Company Name
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'InitialApprovalAmount', 'order' => 'desc', 'page' => 1])); ?>">
                                            Loan Amount
                                        </a>
                                    </th>
                                    <th>City</th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'BorrowerState', 'order' => 'asc', 'page' => 1])); ?>">
                                            State
                                        </a>
                                    </th>
                                    <th>Status</th>
                                    <th>Forgiveness Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $row): ?>
                                    <tr onclick="showModal(<?php echo $index; ?>)">
                                        <td class="click-hint"><?php echo htmlspecialchars($row['BorrowerName'] ?? 'N/A'); ?></td>
                                        <td class="currency">
                                            <?php echo formatCurrency($row['InitialApprovalAmount'] ?? 0); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['BorrowerCity'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['BorrowerState'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['LoanStatus'] ?? 'N/A'); ?></td>
                                        <td class="currency">
                                            <?php 
                                            $forgiveness = $row['ForgivenessAmount'] ?? 0;
                                            echo $forgiveness > 0 ? formatCurrency($forgiveness) : '-';
                                            ?>
                                        </td>
                                    </tr>

                                    <!-- Individual modal for each row -->
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
                                                        <h3>🏢 Borrower Information</h3>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Company Name:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['BorrowerName'] ?? '-'); ?></span>
                                                        </div>
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
                                                            <span class="detail-label">Business Type:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['BusinessType'] ?? '-'); ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="detail-section">
                                                        <h3>💰 Loan Details</h3>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Loan Number:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['LoanNumber'] ?? '-'); ?></span>
                                                        </div>
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
                                                            <span class="detail-label">Date Approved:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['DateApproved'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Forgiveness Date:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['ForgivenessDate'] ?? '-'); ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="detail-section">
                                                        <h3>🏦 Lender Information</h3>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Originating Lender:</span>
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
                                                            <span class="detail-label">Servicing Lender:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['ServicingLenderName'] ?? '-'); ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="detail-section">
                                                        <h3>📊 Additional Details</h3>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Jobs Reported:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['JobsReported'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">NAICS Code:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['NAICSCode'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Franchise Name:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['FranchiseName'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Term (months):</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['Term'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">SBA Guaranty %:</span>
                                                            <span class="detail-value"><?php echo $row['SBAGuarantyPercentage'] ? htmlspecialchars($row['SBAGuarantyPercentage']) . '%' : '-'; ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Gender:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['Gender'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Veteran:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['Veteran'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Non-Profit:</span>
                                                            <span class="detail-value"><?php echo htmlspecialchars($row['NonProfit'] ?? '-'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="detail-section" style="margin-top: 20px;">
                                                    <h3>💵 Fund Usage Breakdown</h3>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Payroll:</span>
                                                        <span class="detail-value currency"><?php echo formatCurrency($row['PAYROLL_PROCEED'] ?? 0); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Rent:</span>
                                                        <span class="detail-value currency"><?php echo formatCurrency($row['RENT_PROCEED'] ?? 0); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Utilities:</span>
                                                        <span class="detail-value currency"><?php echo formatCurrency($row['UTILITIES_PROCEED'] ?? 0); ?></span>
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
                                echo '<a href="?' . http_build_query($pagination_params) . '">← Previous</a>';
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
                                echo '<a href="?' . http_build_query($pagination_params) . '">Next →</a>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        <h3>No results found</h3>
                        <p>Try adjusting your search criteria or click "Show Top Loans" to see high-value loans.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        console.log('JavaScript loaded successfully!');
        
        function showModal(index) {
            console.log('Showing modal for index:', index);
            const modal = document.getElementById('modal' + index);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                console.log('Modal displayed');
            } else {
                console.error('Modal not found for index:', index);
            }
        }

        function closeModal(index) {
            console.log('Closing modal for index:', index);
            const modal = document.getElementById('modal' + index);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal when clicking outside of it
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

        // Test click handler
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up test...');
            const rows = document.querySelectorAll('tbody tr');
            console.log('Found', rows.length, 'table rows');
            
            // Test that modals exist
            const modals = document.querySelectorAll('.modal');
            console.log('Found', modals.length, 'modals');
        });
    </script>
</body>
</html>
                                