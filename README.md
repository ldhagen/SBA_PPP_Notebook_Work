# SBA PPP Loan Data Analysis Pipeline

A comprehensive data processing and analysis pipeline for Small Business Administration (SBA) Paycheck Protection Program (PPP) loan data. This toolkit handles large-scale CSV processing, data harmonization, geographic filtering, and interactive duplicate address analysis.

## Overview

This project processes the complete SBA PPP FOIA dataset (10+ million records, 5GB+ data) using memory-efficient chunked processing techniques. It includes automated web scraping, data harmonization, geographic filtering, and specialized tools for identifying business complexes and shared office spaces with interactive examination capabilities.

## Dataset Information

**Source**: [SBA FOIA PPP Data Portal](https://data.sba.gov/dataset/ppp-foia)

**Data Structure**:
- **Large Loans**: `public_150k_plus_240930.csv` (968K records >$150K loans)  
- **Small Loans**: `public_up_to_150k_[1-12].csv` (10.47M records ≤$150K loans)
- **Total Dataset**: 11+ million PPP loans, 53 columns per record
- **File Sizes**: 4.87GB combined, individual files 270MB-450MB each

## Web Scraping Architecture

The pipeline uses Beautiful Soup to dynamically discover and download the most recent PPP datasets:

### Discovery Process
```python
# 1. Parse main FOIA page
parent_url = "https://data.sba.gov/dataset/ppp-foia"
soup = BeautifulSoup(response.text, "html.parser")
resource_links = [
    "https://data.sba.gov" + a['href']
    for a in soup.select('a[href*="/dataset/ppp-foia/resource/"]')
]
# Result: 28 resource pages discovered

# 2. Extract CSV links from each resource page
for resource_url in resource_links:
    sub_soup = BeautifulSoup(response.text, "html.parser")
    csv_link = sub_soup.select_one('a.resource-url-analytics[href$=".csv"]')
    if csv_link:
        download_urls.append(csv_link['href'])
# Result: 26 CSV files found (2 resource pages contained no CSV links)
```

### Advantages of Dynamic Discovery
- **Future-proof**: Automatically detects new dataset releases
- **No hardcoded URLs**: Adapts to SBA website structure changes  
- **Version awareness**: Always downloads the most recent data snapshots
- **Comprehensive coverage**: Discovers all available CSV resources, not just known files

## Core Features

### 1. Automated Data Collection
- **Web scraping with Beautiful Soup**: Parses SBA FOIA portal HTML to discover latest datasets
- **Dynamic dataset detection**: Automatically finds new CSV releases without hardcoded URLs
- **Intelligent resource extraction**: Follows resource page links to locate actual download URLs
- **Smart caching**: 5-day refresh cycle prevents unnecessary re-downloads
- **Robust error handling**: Retry logic with exponential backoff for failed requests

### 2. Data Processing Pipeline
- **Type Harmonization**: Resolves mixed data types across files
- **Memory-Efficient Processing**: Chunked operations for large datasets
- **Geographic Filtering**: State/ZIP code based extraction
- **Data Validation**: Encoding detection and error handling

### 3. Interactive Address Analysis Tools
- **City-Specific Duplicate Detection**: Finds shared business locations within the same city
- **Interactive Address Explorer**: Scan, filter, and examine addresses with 30+ businesses
- **Individual Address Deep Dive**: Extract and analyze all records for specific locations
- **Business Complex Analysis**: Identifies office buildings, incubators, and shared workspaces
- **Financial Impact Assessment**: Comprehensive loan statistics by location

## Installation & Setup

```bash
# Clone repository and navigate to directory
cd sba-ppp-analysis

# Install dependencies (including Beautiful Soup for web scraping)
pip install pandas numpy beautifulsoup4 requests chardet

# Create data directory
mkdir -p sba_csv/chunks
```

**Dependencies Explained**:
- `pandas`: Data processing and analysis
- `numpy`: Numerical operations and array handling
- `beautifulsoup4`: HTML parsing for SBA website scraping
- `requests`: HTTP requests with retry logic and session management
- `chardet`: Automatic encoding detection for CSV files

## Usage

### Basic Data Download and Processing

```python
# 1. Automated discovery and download of latest PPP data
python CoPilotSuggestedLoadSBAPPPData.ipynb

# Beautiful Soup web scraping process:
# - Accesses https://data.sba.gov/dataset/ppp-foia
# - Parses HTML to find all resource pages (28 found)
# - Extracts CSV download links from each resource page
# - Downloads 26 discovered CSV files (2 pages had no CSV links)
# - Implements smart caching to avoid re-downloading recent files

# Data processing pipeline:
# - Merge small loan files with type harmonization
# - Create combined_public_up_to_150k.csv (4.87GB)
# - Generate Texas-filtered dataset (177K records)
```

### Geographic Analysis

```python
import pandas as pd

# Load Texas-specific data (ready for analysis)
df_tx = pd.read_csv("path/to/texas_filtered_data.csv")

# Basic Texas statistics
print(f"Texas PPP loans: {len(df_tx):,}")
print(f"Total approved: ${df_tx['InitialApprovalAmount'].sum():,.2f}")
print(f"Average loan: ${df_tx['InitialApprovalAmount'].mean():,.2f}")
```

### Interactive Address Analysis

```python
# Interactive scanner with city-specific matching
from address_analysis import interactive_address_explorer

# Scan for addresses with 30+ businesses in the same city
frequent_addresses, address_list = interactive_address_explorer(
    "./sba_csv/combined_public_up_to_150k.csv", 
    min_occurrences=30
)

# Results show address-city combinations to avoid false positives
# Example output:
# 1445 WOODMONT LN NW                     | ATLANTA         (236 businesses)
# 9900 SPECTRUM DR                        | AUSTIN          (32 businesses)
```

### Interactive Address Examination

```python
# Deep dive into a specific business complex
from address_analysis import examine_specific_address, analyze_address_details

# Extract all records for a specific address-city combination
address_data = examine_specific_address(
    filepath, 
    '1445 WOODMONT LN NW', 
    'ATLANTA'
)

# Comprehensive analysis of the business complex
analyze_address_details(address_data, '1445 WOODMONT LN NW', 'ATLANTA')

# View individual loan records
display_cols = ['BorrowerName', 'InitialApprovalAmount', 'DateApproved', 'ForgivenessAmount']
print(address_data[display_cols].head(10))
```

### Legacy Address Analysis

```python
# Find businesses sharing the same address (original method)
from address_analysis import find_duplicate_addresses_chunked

# Scan for addresses with 15+ businesses
duplicates = find_duplicate_addresses_chunked(
    "./sba_csv/combined_public_up_to_150k.csv", 
    min_occurrences=15
)

# Results: Dictionary of {address: count}
for address, count in sorted(duplicates.items(), key=lambda x: x[1], reverse=True)[:10]:
    print(f"{address}: {count} businesses")
```

## Key Findings & Examples

### Interactive Address Analysis Results

**Business Complex Discovery Process**:
1. **Automatic Scanning**: Interactive scanner identifies 200+ address-city combinations with 30+ businesses
2. **City-Specific Matching**: Prevents false positives by matching identical addresses only within the same city
3. **Detailed Examination**: Extract complete loan records and business data for any address
4. **Financial Analysis**: Comprehensive statistics including loan amounts, forgiveness rates, and timeline data

**Major Business Complexes Identified**:

**1445 Woodmont Ln NW, Atlanta** (Example from interactive analysis):
- 541+ individual businesses identified
- 260+ unique suite/unit variations (Suite #126 to #3801)
- $10.5M+ total PPP funding approved
- 77.4% forgiveness rate across all loans
- Business incubator/shared workspace characteristics

**9900 Spectrum Dr, Austin, TX**:
- 32 businesses, $2.1M+ in PPP loans
- Mix of LLCs, sole proprietorships, corporations
- Technology and consulting focus

### Interactive Analysis Features

**City-Specific Detection**: The enhanced scanner prevents false positives by ensuring "Main St" in Chicago doesn't get combined with "Main St" in Phoenix, providing accurate business complex identification.

**Deep Dive Capabilities**:
- Individual loan record extraction for any address
- Business diversity analysis (unique names vs. repeat borrowers)
- Timeline analysis showing peak loan months
- Forgiveness rate calculations by location
- Financial impact summaries with min/max/average loan sizes

**Sample Interactive Output**:
```
DETAILED ANALYSIS: 1445 WOODMONT LN NW, ATLANTA
Total businesses/loans: 541

Financial Impact:
  Total PPP loans approved: $10,492,883.65
  Average loan size: $19,395.35
  Forgiveness rate: 77.4% (419/541 loans)

Business Diversity:
  Unique business names: 467
  Top repeat borrowers:
    KIBAYI SHISSO (3 loans)
    ENIYA BROWN (2 loans)
```

### Data Quality Insights

**Type Harmonization Issues Resolved**:
- `SBAOfficeCode`: Mixed int64/float64 → object
- `JobsReported`: Mixed int64/float64 → object  
- `ServicingLenderLocationID`: Mixed types → object
- `OriginatingLenderLocationID`: Mixed types → object

## File Structure

```
sba-ppp-analysis/
├── README.md
├── CoPilotSuggestedLoadSBAPPPData.ipynb    # Main processing notebook
├── interactive_address_scanner.py            # Interactive address analysis tools
├── address_analysis.py                      # Legacy address scanning tools
├── sba_csv/
│   ├── public_150k_plus_240930.csv        # Large loans (968K records)
│   ├── public_up_to_150k_[1-12].csv       # Small loan files
│   ├── combined_public_up_to_150k.csv     # Merged small loans (10.47M)
│   ├── schema_summary.csv                  # Data type documentation
│   └── chunks/                             # Processing intermediates
├── results/
│   ├── texas_ppp_analysis.csv             # Filtered Texas data
│   ├── frequent_addresses_30plus.csv       # Interactive address analysis results
│   ├── duplicate_addresses_report.csv      # Legacy address analysis results
│   └── business_complex_analysis.csv       # Shared location insights
└── logs/
    └── sba_download.log                    # Processing logs
```

## Performance Specifications

**System Requirements**:
- RAM: 8GB minimum, 16GB recommended
- Storage: 10GB free space for full dataset
- Processing Time: 2-3 hours for complete pipeline

**Processing Stats**:
- **Discovery Phase**: ~30 seconds to scrape and parse 28 resource pages
- **Download Phase**: 2-3 minutes per CSV file (26 files total)
- **Large File Processing**: ~15 minutes for 4.87GB combined file
- **Address Analysis**: ~20 minutes for full dataset scan
- **Total Pipeline Runtime**: 2-3 hours for complete end-to-end processing
- **Memory Usage**: Peak 2GB during chunked processing operations

## Data Schema

**Core Fields** (53 total columns):
- `LoanNumber`: Unique loan identifier
- `BorrowerName`: Business name
- `BorrowerAddress`: Business address (key for analysis)
- `BorrowerCity`, `BorrowerState`, `BorrowerZip`: Location data
- `InitialApprovalAmount`: Original loan amount
- `ForgivenessAmount`: Amount forgiven (if applicable)
- `DateApproved`: Loan approval date
- `NAICSCode`: Industry classification
- `JobsReported`: Jobs supported/retained
- `ProcessingMethod`: PPP vs PPS (first vs second draw)

## Advanced Analysis Examples

### Industry Analysis by Geography
```python
# Texas industry breakdown
tx_industries = df_tx.groupby('NAICSCode').agg({
    'InitialApprovalAmount': ['count', 'sum', 'mean']
}).round(2)
```

### Timeline Analysis
```python
# Monthly PPP distribution
df_tx['DateApproved'] = pd.to_datetime(df_tx['DateApproved'])
monthly_stats = df_tx.groupby(df_tx['DateApproved'].dt.to_period('M')).agg({
    'InitialApprovalAmount': ['count', 'sum']
})
```

### Forgiveness Rate Analysis
```python
# Calculate forgiveness rates
forgiveness_rate = df_tx['ForgivenessAmount'].notna().mean() * 100
avg_forgiveness = df_tx['ForgivenessAmount'].mean()
```

## Known Limitations

1. **Memory Constraints**: Full dataset requires chunked processing
2. **Address Standardization**: Manual cleanup needed for some address variations  
3. **Data Currency**: Snapshot as of September 2024 (240930)
4. **Geographic Coverage**: ZIP-based filtering may miss edge cases

## Contributing

This toolkit is designed for researchers, analysts, and policy makers studying small business support programs. Key areas for contribution:

- **Interactive Analysis Tools**: Enhanced GUI interfaces for address exploration and business complex visualization
- **Web Scraping Enhancements**: Improved Beautiful Soup selectors for SBA website changes
- **Address Standardization**: Enhanced fuzzy matching algorithms for business location analysis with city-specific accuracy
- **Industry Analysis**: NAICS code clustering and visualization tools with geographic overlays
- **Geographic Tools**: County/MSA level aggregation beyond ZIP code filtering
- **Performance Optimization**: Parallel processing implementations for faster chunked operations
- **Dataset Validation**: Automated checks for data quality and completeness across scraping runs
- **Business Intelligence**: Machine learning models for identifying business relationship patterns in shared locations

## License & Disclaimer

This project processes public domain data from the SBA. The analysis code is provided for educational and research purposes. Users should verify findings independently and respect privacy considerations when analyzing business data.

**Data Source**: U.S. Small Business Administration FOIA PPP Data
**Last Updated**: September 2024
**Processing Date**: September 1, 2025
