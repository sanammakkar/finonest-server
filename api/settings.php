<?php
require_once __DIR__ . '/../cors-handler.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

function requireAdmin() {
    $headers = getallheaders() ?: [];
    $token = null;

    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            if (preg_match('/Bearer\s(\S+)/', $value, $matches)) {
                $token = $matches[1];
            }
            break;
        }
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit();
    }

    try {
        $decoded = JWT::decode($token);
        if (!$decoded || !isset($decoded['role']) || $decoded['role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            exit();
        }
        return $decoded;
    } catch (Exception $e) {
        error_log('JWT decode error: ' . $e->getMessage());
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token: ' . $e->getMessage()]);
        exit();
    }
}

// Create settings table if it doesn't exist
try {
    $createTable = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($createTable);
    
    // Insert default settings if they don't exist
    $defaultSettings = [
        ['razorpay_key', 'rzp_test_default', 'Razorpay API Key for payments'],
        ['razorpay_secret', '', 'Razorpay Secret Key (keep secure)'],
        ['site_name', 'Finonest', 'Website name'],
        ['contact_email', 'info@finonest.com', 'Contact email address'],
        ['gemini_api_key', 'AIzaSyDZ8XZq09tzFqvuTAbcJlQscS_WUNDbkAI', 'Google Gemini API key for AI features'],
        ['gemini_model', 'gemini-2.5-flash-lite', 'Gemini model for AI operations'],
        ['ai_enabled', 'enabled', 'Enable or disable AI features'],
        ['pan_api_url', 'https://profilex-api.neokred.tech/core-svc/api/v2/exp/validation-service/pan-premium', 'PAN verification API URL'],
        ['pan_client_user_id', '', 'PAN API client user ID'],
        ['pan_secret_key', '', 'PAN API secret key'],
        ['pan_access_key', '', 'PAN API access key'],
        ['credit_api_url', 'https://profilex-api.neokred.tech/core-svc/api/v2/exp/user-profiling/credit-report', 'Credit report API URL'],
        ['credit_client_user_id', '', 'Credit API client user ID'],
        ['credit_secret_key', '', 'Credit API secret key'],
        ['credit_access_key', '', 'Credit API access key'],
        ['credit_service_id', '', 'Credit API service ID'],
        ['client_user_id', '', 'Client User ID for API authentication'],
        ['base_url', '', 'Base URL for API calls'],
        ['secret_key', '', 'Secret key for API authentication'],
        ['access_key', '', 'Access key for API authentication'],
        ['service_id', '', 'Service ID for Credit API'],
        ['surepass_api_url', 'https://kyc-api.surepass.io/api/v1/rc/rc-full', 'SurePass RC API URL'],
        ['surepass_token', '', 'SurePass API token'],
        ['blog_recent_days', '15', 'Number of days to consider a blog post as recent on blog page'],
        ['privacy_policy', '', 'Privacy policy HTML content for public page'],
        ['privacy_policy_sections_json', '', 'Privacy policy section-wise JSON for admin legal page editor'],
        ['terms_and_conditions_sections_json', '', 'Terms section-wise JSON for admin legal page editor'],
        // Default SEO meta settings for main pages (can be customized from admin panel)
        ['meta_home_title', 'Finonest | India\'s Fastest Growing Loan Provider', 'Home page meta title'],
        ['meta_home_description', 'Finonest - India\'s fastest growing loan provider. Get home loans, car loans, personal loans & business loans with quick approval and best interest rates from 50+ banks.', 'Home page meta description'],
        ['meta_home_keywords', 'loan, home loan, car loan, personal loan, business loan, used car loan, loan against property, credit card, EMI calculator, credit score, Finonest, financial services India, Jaipur', 'Home page meta keywords'],
        ['meta_about_title', 'About Finonest | India\'s Trusted Loan Partner', 'About page meta title'],
        ['meta_about_description', 'Learn about Finonest India Pvt. Ltd., India\'s trusted loan partner helping individuals and businesses with customized financial solutions.', 'About page meta description'],
        ['meta_about_keywords', 'about finonest, about us, loan company, financial services, Jaipur, India', 'About page meta keywords'],
        ['meta_services_title', 'Loan Services | Finonest', 'Services page meta title'],
        ['meta_services_description', 'Explore Finonest loan services including home loans, personal loans, business loans, used car loans, and loans against property from 50+ partner banks.', 'Services page meta description'],
        ['meta_services_keywords', 'loan services, home loan, personal loan, business loan, Finonest services', 'Services page meta keywords'],
        ['meta_contact_title', 'Contact Finonest | Get Loan Assistance', 'Contact page meta title'],
        ['meta_contact_description', 'Contact Finonest for any loan related queries, assistance, or support. Our team is here to help you choose the right financial product.', 'Contact page meta description'],
        ['meta_contact_keywords', 'contact finonest, loan support, loan enquiry, customer care', 'Contact page meta keywords'],
        ['meta_blog_title', 'Finonest Blog | Financial Tips & Loan Guides', 'Blog page meta title'],
        ['meta_blog_description', 'Read Finonest blog for financial tips, loan guides, credit score improvement ideas, and latest updates in the finance world.', 'Blog page meta description'],
        ['meta_blog_keywords', 'finance blog, loan tips, credit score tips, Finonest blog', 'Blog page meta keywords'],
        // Service pages SEO meta (loans & credit cards)
        ['meta_service_home_loan_title', 'Home Loan in India - Best Rates from 8.35% | Finonest | Up to ₹5 Crore', 'Home Loan service page meta title'],
        ['meta_service_home_loan_description', 'Get best home loan in India starting at 8.35% p.a. with Finonest. Compare 50+ banks, up to ₹5Cr financing, 30-year tenure, instant approval. Trusted home loan provider in Jaipur & across India.', 'Home Loan service page meta description'],
        ['meta_service_home_loan_keywords', 'home loan in India, best home loan provider India, housing loan, home loan Jaipur, lowest home loan interest rate, home loan 2024, property loan India, home finance, mortgage loan India', 'Home Loan service page meta keywords'],
        ['meta_service_car_loan_title', 'Car Loan - Finonest | Rates from 7.99% | 100% Financing Available', 'Car Loan service page meta title'],
        ['meta_service_car_loan_description', 'Drive your dream car with Finonest car loans. Interest rates from 7.99% p.a., 100% financing, same-day approval. New & used car loans available. Apply now!', 'Car Loan service page meta description'],
        ['meta_service_car_loan_keywords', 'car loan, auto loan, vehicle loan, new car finance, used car loan, best car loan rates India', 'Car Loan service page meta keywords'],
        ['meta_service_used_car_loan_title', 'Used Car Loan in India - Best Rates 11.5% | Finonest | Up to ₹1.5 Crore', 'Used Car Loan service page meta title'],
        ['meta_service_used_car_loan_description', 'Get best used car loan in India starting at 11.5% p.a. with Finonest. Finance up to 90% of car value, up to ₹1.5 crore, 7-year tenure. Trusted used car loan provider - compare 35+ banks.', 'Used Car Loan service page meta description'],
        ['meta_service_used_car_loan_keywords', 'used car loan in India, best used car loan provider, second hand car loan, pre-owned car finance, used car loan Jaipur, used car EMI calculator, used car loan low interest rate 2024', 'Used Car Loan service page meta keywords'],
        ['meta_service_new_car_loan_title', 'New Car Loan in India - Best Rates 8.5% | Finonest | Up to ₹2 Crore', 'New Car Loan service page meta title'],
        ['meta_service_new_car_loan_description', 'Get best new car loan in India starting at 8.5% p.a. with Finonest. Finance up to 100% on-road price, up to ₹2 crore, 8-year tenure. Compare 35+ banks.', 'New Car Loan service page meta description'],
        ['meta_service_new_car_loan_keywords', 'new car loan india, new car finance, car loan low interest, new car loan jaipur, best new car loan 2024', 'New Car Loan service page meta keywords'],
        ['meta_service_car_refinance_title', 'Car Refinance / Balance Transfer - Lower EMI | Finonest India', 'Car Refinance service page meta title'],
        ['meta_service_car_refinance_description', 'Refinance your car loan with Finonest. Lower your EMI, reduce interest rate, get top-up loan. Compare 35+ banks. Quick balance transfer with minimal documentation.', 'Car Refinance service page meta description'],
        ['meta_service_car_refinance_keywords', 'car refinance india, car loan balance transfer, lower car emi, car loan refinance jaipur, reduce car loan interest', 'Car Refinance service page meta keywords'],
        ['meta_service_personal_loan_title', 'Personal Loan in India - Instant Approval up to ₹40 Lakhs | Finonest', 'Personal Loan service page meta title'],
        ['meta_service_personal_loan_description', 'Get instant personal loan in India up to ₹40 lakhs at 10.49% p.a. with Finonest. Best personal loan provider - minimal docs, 24-hour disbursal, no collateral. Apply online now!', 'Personal Loan service page meta description'],
        ['meta_service_personal_loan_keywords', 'personal loan in India, best personal loan provider India, instant personal loan, personal loan Jaipur, quick personal loan, unsecured loan India, personal loan low interest rate, personal loan 2024', 'Personal Loan service page meta keywords'],
        ['meta_service_business_loan_title', 'Business Loan in India - Up to ₹5 Crore | MSME Loan | Finonest', 'Business Loan service page meta title'],
        ['meta_service_business_loan_description', 'Get best business loan in India up to ₹5 crore with Finonest. Collateral-free MSME loan up to ₹75 lakhs, rates from 14% p.a., 72-hour disbursal. Trusted business loan provider in India.', 'Business Loan service page meta description'],
        ['meta_service_business_loan_keywords', 'business loan in India, MSME loan India, best business loan provider, working capital loan, collateral free business loan, business loan Jaipur, small business loan India, term loan India, business finance 2024', 'Business Loan service page meta keywords'],
        ['meta_service_lap_title', 'Loan Against Property - Finonest | Up to 70% LTV | Low Rates 8.75%', 'Loan Against Property service page meta title'],
        ['meta_service_lap_description', 'Unlock your property\'s value with Finonest LAP. Get up to 70% of property value, rates from 8.75% p.a., 20-year tenure. Residential & commercial properties accepted.', 'Loan Against Property service page meta description'],
        ['meta_service_lap_keywords', 'loan against property, LAP, property loan, mortgage loan, home equity loan, secured loan India', 'Loan Against Property service page meta keywords'],
        ['meta_service_credit_cards_title', 'Credit Cards - Finonest | Best Rewards, Travel & Cashback Cards', 'Credit Cards service page meta title'],
        ['meta_service_credit_cards_description', 'Compare and apply for the best credit cards in India with Finonest. Rewards, travel, cashback cards from HDFC, ICICI, SBI & more. Instant approval available.', 'Credit Cards service page meta description'],
        ['meta_service_credit_cards_keywords', 'credit card, best credit card India, rewards credit card, travel credit card, cashback card, credit card apply', 'Credit Cards service page meta keywords'],
        // DSA Registration & product-specific pages
        ['meta_dsa_registration_title', 'DSA Partner Registration - Finonest | Join Our Network', 'DSA Partner Registration page meta title'],
        ['meta_dsa_registration_description', 'Register as DSA Partner with Finonest. Start your loan business with zero investment. Fill the registration form and start earning today!', 'DSA Partner Registration page meta description'],
        ['meta_dsa_registration_keywords', 'dsa registration, finonest dsa, dsa partner form, loan partner registration', 'DSA Partner Registration page meta keywords'],
        ['meta_dsa_home_loans_title', 'Apply as DSA for Home Loans - Finonest | Earn High Commission', 'DSA Home Loans page meta title'],
        ['meta_dsa_home_loans_description', 'Register as DSA Partner for Home Loans with Finonest. Earn high commissions on every disbursed home loan. Zero investment. Apply now!', 'DSA Home Loans page meta description'],
        ['meta_dsa_home_loans_keywords', 'dsa home loan, home loan dsa partner, finonest home loan agent, earn commission home loan', 'DSA Home Loans page meta keywords'],
        ['meta_dsa_personal_loans_title', 'Apply as DSA for Personal Loans - Finonest | Earn High Commission', 'DSA Personal Loans page meta title'],
        ['meta_dsa_personal_loans_description', 'Register as DSA Partner for Personal Loans with Finonest. Earn high commissions on every disbursed personal loan. Zero investment. Apply now!', 'DSA Personal Loans page meta description'],
        ['meta_dsa_personal_loans_keywords', 'dsa personal loan, personal loan dsa partner, finonest personal loan agent, earn commission personal loan', 'DSA Personal Loans page meta keywords'],
        ['meta_dsa_business_loans_title', 'Apply as DSA for Business Loans - Finonest | Earn High Commission', 'DSA Business Loans page meta title'],
        ['meta_dsa_business_loans_description', 'Register as DSA Partner for Business Loans with Finonest. Earn high commissions on every disbursed business loan. Zero investment. Apply now!', 'DSA Business Loans page meta description'],
        ['meta_dsa_business_loans_keywords', 'dsa business loan, business loan dsa partner, finonest business loan agent, earn commission business loan', 'DSA Business Loans page meta keywords'],
        ['meta_dsa_car_loans_title', 'Apply as DSA for Car Loans - Finonest | Earn High Commission', 'DSA Car Loans page meta title'],
        ['meta_dsa_car_loans_description', 'Register as DSA Partner for Car Loans with Finonest. Earn high commissions on every disbursed car loan. Zero investment. Apply now!', 'DSA Car Loans page meta description'],
        ['meta_dsa_car_loans_keywords', 'dsa car loan, car loan dsa partner, finonest car loan agent, earn commission car loan', 'DSA Car Loans page meta keywords'],
        ['meta_dsa_loan_against_property_title', 'Apply as DSA for Loan Against Property - Finonest | Earn High Commission', 'DSA LAP page meta title'],
        ['meta_dsa_loan_against_property_description', 'Register as DSA Partner for Loan Against Property with Finonest. Earn high commissions on every disbursed LAP. Zero investment. Apply now!', 'DSA LAP page meta description'],
        ['meta_dsa_loan_against_property_keywords', 'dsa lap, loan against property dsa, finonest lap agent, earn commission lap', 'DSA LAP page meta keywords'],
        ['meta_dsa_education_loans_title', 'Apply as DSA for Education Loans - Finonest | Earn High Commission', 'DSA Education Loans page meta title'],
        ['meta_dsa_education_loans_description', 'Register as DSA Partner for Education Loans with Finonest. Earn high commissions on every disbursed education loan. Zero investment. Apply now!', 'DSA Education Loans page meta description'],
        ['meta_dsa_education_loans_keywords', 'dsa education loan, education loan dsa partner, finonest education loan agent', 'DSA Education Loans page meta keywords'],
        ['meta_dsa_credit_cards_title', 'Apply as DSA for Credit Cards - Finonest | Earn High Commission', 'DSA Credit Cards page meta title'],
        ['meta_dsa_credit_cards_description', 'Register as DSA Partner for Credit Cards with Finonest. Earn high commissions on every approved credit card. Zero investment. Apply now!', 'DSA Credit Cards page meta description'],
        ['meta_dsa_credit_cards_keywords', 'dsa credit card, credit card dsa partner, finonest credit card agent, earn commission credit card', 'DSA Credit Cards page meta keywords'],
        ['meta_dsa_insurance_products_title', 'Apply as DSA for Insurance Products - Finonest | Earn High Commission', 'DSA Insurance page meta title'],
        ['meta_dsa_insurance_products_description', 'Register as DSA Partner for Insurance Products with Finonest. Earn high commissions on every policy sold. Zero investment. Apply now!', 'DSA Insurance page meta description'],
        ['meta_dsa_insurance_products_keywords', 'dsa insurance, insurance dsa partner, finonest insurance agent, earn commission insurance', 'DSA Insurance page meta keywords'],
        // Other form pages
        ['meta_banker_form_title', 'Banker Form - Finonest', 'Banker Form page meta title'],
        ['meta_branches_description', 'Find all Finonest branches across India. View addresses, contact details, and directions to your nearest Finonest branch.', 'Branches page meta description'],
        ['meta_branches_keywords', 'finonest branches, loan branches, finonest locations, loan office near me, finonest branch Jaipur', 'Branches page meta keywords'],
        ['meta_cibil_title', 'Check Credit Score - Finonest | Free Credit Score Check', 'Credit score (CIBIL) page meta title'],
        ['meta_cibil_description', 'Check your credit score for free with Finonest. Get your credit report and understand your credit health. Improve your chances of loan approval.', 'Credit score (CIBIL) page meta description'],
        ['meta_cibil_keywords', 'credit score, credit score check, free credit report, Finonest, credit rating', 'Credit score (CIBIL) page meta keywords'],
        ['meta_dsa_partner_title', 'Become DSA Partner in India - Earn High Commission | Finonest', 'DSA Partner page meta title'],
        ['meta_dsa_partner_description', 'Become a DSA Partner with Finonest - India\'s trusted loan distribution network. Zero investment, high commission on home loan, personal loan, car loan disbursals. Register as loan agent today!', 'DSA Partner page meta description'],
        ['meta_dsa_partner_keywords', 'DSA partner India, become loan agent, DSA registration, loan DSA partner, earn commission on loans, loan agent business India, DSA partner Jaipur, financial services partner, loan distributor India', 'DSA Partner page meta keywords'],
        ['meta_emi_calculator_title', 'EMI Calculator - Finonest | Calculate Your Loan EMI', 'EMI Calculator page meta title'],
        ['meta_emi_calculator_description', 'Use Finonest\'s free EMI calculator to calculate your monthly loan payments. Works for home loans, car loans, personal loans, and more.', 'EMI Calculator page meta description'],
        ['meta_emi_calculator_keywords', 'EMI calculator, loan calculator, home loan EMI, car loan EMI, personal loan EMI', 'EMI Calculator page meta keywords'],
        ['meta_apply_title', 'Apply for Loan - Finonest | Quick & Easy Application', 'Apply page meta title'],
        ['meta_apply_description', 'Apply for home loans, car loans, personal loans, and more with Finonest. Quick approval within 24 hours.', 'Apply page meta description'],
        ['meta_apply_keywords', 'apply loan, loan application, home loan apply, personal loan apply, finonest apply', 'Apply page meta keywords'],
        ['meta_careers_title', 'Careers - Finonest | Join Our Team', 'Careers page meta title'],
        ['meta_careers_description', 'Explore career opportunities at Finonest and build your future in financial services.', 'Careers page meta description'],
        ['meta_careers_keywords', 'finonest careers, jobs in finance, loan company jobs, career opportunities', 'Careers page meta keywords']
    ];
    
    foreach ($defaultSettings as $setting) {
        $checkQuery = "SELECT id FROM system_settings WHERE setting_key = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$setting[0]]);
        
        if ($checkStmt->rowCount() === 0) {
            $insertQuery = "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->execute($setting);
        }
    }
} catch (PDOException $e) {
    error_log('Table creation error: ' . $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Handle specific setting requests
if (preg_match('/\/api\/settings\/(.+)/', $path, $matches)) {
    $setting_key = $matches[1];
    
    // Normalize key (convert hyphens to underscores)
    $setting_key = str_replace('-', '_', $setting_key);
    
    switch($method) {
        case 'GET':
            getSetting($setting_key);
            break;
        case 'PUT':
            updateSetting($setting_key);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} else {
    switch($method) {
        case 'GET':
            getAllSettings();
            break;
        case 'POST':
            createSetting();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function getSetting($key) {
    global $db;
    
    // Public access for certain settings (no auth required)
    $publicSettings = ['razorpay_key', 'site_name', 'blog_recent_days', 'privacy_policy', 'terms_and_conditions'];
    
    // Allow public access for SEO meta settings (keys starting with "meta_")
    $isPublicMetaKey = str_starts_with($key, 'meta_');
    
    if (!in_array($key, $publicSettings) && !$isPublicMetaKey) {
        requireAdmin();
    }
    
    try {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'key' => $result['setting_value']
            ]);
        } else {
            // Return default for razorpay_key if not found
            if ($key === 'razorpay_key') {
                echo json_encode([
                    'success' => true,
                    'key' => 'rzp_test_default'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Setting not found']);
            }
        }
    } catch (Exception $e) {
        error_log('Error in getSetting: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get setting']);
    }
}

function getAllSettings() {
    global $db;
    
    requireAdmin();
    
    try {
        $query = "SELECT setting_key, setting_value, description, updated_at FROM system_settings ORDER BY setting_key";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        error_log('Error in getAllSettings: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get settings']);
    }
}

function updateSetting($key) {
    global $db;
    
    try {
        requireAdmin();
    } catch (Exception $e) {
        error_log('Admin auth error: ' . $e->getMessage());
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['value'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }
    
    $value = $input['value'];
    
    try {
        // First try to update existing setting
        $query = "UPDATE system_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$value, $key]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Setting updated successfully'
            ]);
        } else {
            // Setting doesn't exist, create it
            $insertQuery = "INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            $insertStmt = $db->prepare($insertQuery);
            $description = getSettingDescription($key);
            $insertStmt->execute([$key, $value, $description]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Setting created successfully'
            ]);
        }
    } catch (Exception $e) {
        error_log('Error in updateSetting: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSettingDescription($key) {
    $descriptions = [
        'gemini_api_key' => 'Google Gemini API key for AI features',
        'gemini_model' => 'Gemini model for AI operations',
        'ai_enabled' => 'Enable or disable AI features',
        'razorpay_key' => 'Razorpay API Key for payments',
        'razorpay_secret' => 'Razorpay Secret Key (keep secure)',
        'site_name' => 'Website name',
        'contact_email' => 'Contact email address',
        'pan_api_url' => 'PAN verification API URL',
        'pan_client_user_id' => 'PAN API client user ID',
        'pan_secret_key' => 'PAN API secret key',
        'pan_access_key' => 'PAN API access key',
        'credit_api_url' => 'Credit report API URL',
        'credit_client_user_id' => 'Credit API client user ID',
        'credit_secret_key' => 'Credit API secret key',
        'credit_access_key' => 'Credit API access key',
        'credit_service_id' => 'Credit API service ID',
        'surepass_api_url' => 'SurePass RC API URL',
        'client_user_id' => 'Client User ID for API authentication',
        'base_url' => 'Base URL for API calls',
        'secret_key' => 'Secret key for API authentication',
        'access_key' => 'Access key for API authentication',
        'service_id' => 'Service ID for Credit API',
        // SEO meta descriptions
        'meta_home_title' => 'Home page meta title',
        'meta_home_description' => 'Home page meta description',
        'meta_home_keywords' => 'Home page meta keywords',
        'meta_about_title' => 'About page meta title',
        'meta_about_description' => 'About page meta description',
        'meta_about_keywords' => 'About page meta keywords',
        'meta_services_title' => 'Services page meta title',
        'meta_services_description' => 'Services page meta description',
        'meta_services_keywords' => 'Services page meta keywords',
        'meta_contact_title' => 'Contact page meta title',
        'meta_contact_description' => 'Contact page meta description',
        'meta_contact_keywords' => 'Contact page meta keywords',
        'meta_blog_title' => 'Blog page meta title',
        'meta_blog_description' => 'Blog page meta description',
        'meta_blog_keywords' => 'Blog page meta keywords',
        // Service pages SEO descriptions
        'meta_service_home_loan_title' => 'Home Loan service page meta title',
        'meta_service_home_loan_description' => 'Home Loan service page meta description',
        'meta_service_home_loan_keywords' => 'Home Loan service page meta keywords',
        'meta_service_car_loan_title' => 'Car Loan service page meta title',
        'meta_service_car_loan_description' => 'Car Loan service page meta description',
        'meta_service_car_loan_keywords' => 'Car Loan service page meta keywords',
        'meta_service_used_car_loan_title' => 'Used Car Loan service page meta title',
        'meta_service_used_car_loan_description' => 'Used Car Loan service page meta description',
        'meta_service_used_car_loan_keywords' => 'Used Car Loan service page meta keywords',
        'meta_service_new_car_loan_title' => 'New Car Loan service page meta title',
        'meta_service_new_car_loan_description' => 'New Car Loan service page meta description',
        'meta_service_new_car_loan_keywords' => 'New Car Loan service page meta keywords',
        'meta_service_car_refinance_title' => 'Car Refinance service page meta title',
        'meta_service_car_refinance_description' => 'Car Refinance service page meta description',
        'meta_service_car_refinance_keywords' => 'Car Refinance service page meta keywords',
        'meta_service_personal_loan_title' => 'Personal Loan service page meta title',
        'meta_service_personal_loan_description' => 'Personal Loan service page meta description',
        'meta_service_personal_loan_keywords' => 'Personal Loan service page meta keywords',
        'meta_service_business_loan_title' => 'Business Loan service page meta title',
        'meta_service_business_loan_description' => 'Business Loan service page meta description',
        'meta_service_business_loan_keywords' => 'Business Loan service page meta keywords',
        'meta_service_lap_title' => 'Loan Against Property service page meta title',
        'meta_service_lap_description' => 'Loan Against Property service page meta description',
        'meta_service_lap_keywords' => 'Loan Against Property service page meta keywords',
        'meta_service_credit_cards_title' => 'Credit Cards service page meta title',
        'meta_service_credit_cards_description' => 'Credit Cards service page meta description',
        'meta_service_credit_cards_keywords' => 'Credit Cards service page meta keywords',
        // Other feature/utility page descriptions
        'meta_branches_title' => 'Branches page meta title',
        'meta_branches_description' => 'Branches page meta description',
        'meta_branches_keywords' => 'Branches page meta keywords',
        'meta_cibil_title' => 'Credit score (CIBIL) page meta title',
        'meta_cibil_description' => 'Credit score (CIBIL) page meta description',
        'meta_cibil_keywords' => 'Credit score (CIBIL) page meta keywords',
        'meta_dsa_partner_title' => 'DSA Partner page meta title',
        'meta_dsa_partner_description' => 'DSA Partner page meta description',
        'meta_dsa_partner_keywords' => 'DSA Partner page meta keywords',
        'meta_emi_calculator_title' => 'EMI Calculator page meta title',
        'meta_emi_calculator_description' => 'EMI Calculator page meta description',
        'meta_emi_calculator_keywords' => 'EMI Calculator page meta keywords',
        'meta_dsa_registration_title' => 'DSA Partner Registration page meta title',
        'meta_dsa_registration_description' => 'DSA Partner Registration page meta description',
        'meta_dsa_registration_keywords' => 'DSA Partner Registration page meta keywords',
        'meta_dsa_home_loans_title' => 'DSA Home Loans page meta title',
        'meta_dsa_home_loans_description' => 'DSA Home Loans page meta description',
        'meta_dsa_home_loans_keywords' => 'DSA Home Loans page meta keywords',
        'meta_dsa_personal_loans_title' => 'DSA Personal Loans page meta title',
        'meta_dsa_personal_loans_description' => 'DSA Personal Loans page meta description',
        'meta_dsa_personal_loans_keywords' => 'DSA Personal Loans page meta keywords',
        'meta_dsa_business_loans_title' => 'DSA Business Loans page meta title',
        'meta_dsa_business_loans_description' => 'DSA Business Loans page meta description',
        'meta_dsa_business_loans_keywords' => 'DSA Business Loans page meta keywords',
        'meta_dsa_car_loans_title' => 'DSA Car Loans page meta title',
        'meta_dsa_car_loans_description' => 'DSA Car Loans page meta description',
        'meta_dsa_car_loans_keywords' => 'DSA Car Loans page meta keywords',
        'meta_dsa_loan_against_property_title' => 'DSA Loan Against Property page meta title',
        'meta_dsa_loan_against_property_description' => 'DSA Loan Against Property page meta description',
        'meta_dsa_loan_against_property_keywords' => 'DSA Loan Against Property page meta keywords',
        'meta_dsa_education_loans_title' => 'DSA Education Loans page meta title',
        'meta_dsa_education_loans_description' => 'DSA Education Loans page meta description',
        'meta_dsa_education_loans_keywords' => 'DSA Education Loans page meta keywords',
        'meta_dsa_credit_cards_title' => 'DSA Credit Cards page meta title',
        'meta_dsa_credit_cards_description' => 'DSA Credit Cards page meta description',
        'meta_dsa_credit_cards_keywords' => 'DSA Credit Cards page meta keywords',
        'meta_dsa_insurance_products_title' => 'DSA Insurance Products page meta title',
        'meta_dsa_insurance_products_description' => 'DSA Insurance Products page meta description',
        'meta_dsa_insurance_products_keywords' => 'DSA Insurance Products page meta keywords',
        'meta_banker_form_title' => 'Banker Form page meta title',
        'meta_banker_form_description' => 'Banker Form page meta description',
        'meta_banker_form_keywords' => 'Banker Form page meta keywords',
        'meta_loan_onboarding_title' => 'Loan Onboarding page meta title',
        'meta_loan_onboarding_description' => 'Loan Onboarding page meta description',
        'meta_loan_onboarding_keywords' => 'Loan Onboarding page meta keywords',
        'meta_form_success_title' => 'Form Success page meta title',
        'meta_form_success_description' => 'Form Success page meta description',
        'meta_form_success_keywords' => 'Form Success page meta keywords',
        'meta_service_apply_title' => 'Service Apply page meta title',
        'meta_service_apply_description' => 'Service Apply page meta description',
        'meta_service_apply_keywords' => 'Service Apply page meta keywords',
        'meta_banking_partners_title' => 'Banking Partners page meta title',
        'meta_banking_partners_description' => 'Banking Partners page meta description',
        'meta_banking_partners_keywords' => 'Banking Partners page meta keywords',
        'meta_apply_title' => 'Apply page meta title',
        'meta_apply_description' => 'Apply page meta description',
        'meta_apply_keywords' => 'Apply page meta keywords',
        'meta_careers_title' => 'Careers page meta title',
        'meta_careers_description' => 'Careers page meta description',
        'meta_careers_keywords' => 'Careers page meta keywords',
        'privacy_policy' => 'Privacy policy HTML content for public page',
        'privacy_policy_sections_json' => 'Privacy policy section-wise JSON for admin legal page editor',
        'terms_and_conditions_sections_json' => 'Terms section-wise JSON for admin legal page editor',
    ];
    return $descriptions[$key] ?? '';
}

function createSetting() {
    global $db;
    
    requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $key = $input['key'] ?? '';
    $value = $input['value'] ?? '';
    $description = $input['description'] ?? '';
    
    if (empty($key)) {
        http_response_code(400);
        echo json_encode(['error' => 'Setting key is required']);
        return;
    }
    
    try {
        $query = "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$key, $value, $description]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting created successfully'
        ]);
    } catch (Exception $e) {
        error_log('Error in createSetting: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create setting']);
    }
}
?>