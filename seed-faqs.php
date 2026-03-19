<?php
// ONE-TIME SEED SCRIPT - Run once then delete
// Access: https://api.finonest.com/seed-faqs.php

require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$db->exec("CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page VARCHAR(100) NOT NULL DEFAULT 'home',
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

try { $db->exec("ALTER TABLE faqs ADD COLUMN IF NOT EXISTS page VARCHAR(100) NOT NULL DEFAULT 'home'"); } catch(Exception $e) {}

$faqs = [
  // HOME PAGE
  ['home', "What are the basic eligibility criteria for a loan?", "The basic eligibility criteria include: Age between 21-65 years, Indian citizenship or resident, stable income source (salaried or self-employed), minimum monthly income of ₹15,000, and a good credit score (650+). Specific requirements may vary based on the loan type."],
  ['home', "Can I apply for a loan if I have a low credit score?", "Yes, we work with multiple lending partners including NBFCs that offer loans to individuals with lower credit scores. While interest rates may be slightly higher, we help you find the best possible option. We also provide credit improvement guidance."],
  ['home', "Is there an age limit for applying for a home loan?", "For home loans, the minimum age is 21 years and the loan tenure should end before you turn 70 years. Some banks may extend this to 75 years for certain profiles. Self-employed professionals may have different age criteria."],
  ['home', "What documents are required for a personal loan?", "For salaried individuals: PAN card, Aadhaar card, last 3 months salary slips, 6 months bank statements, and address proof. For self-employed: Additionally ITR for last 2 years, business registration documents, and business bank statements."],
  ['home', "Do I need to visit your office to submit documents?", "No! We offer 100% digital document submission. You can upload documents through our secure portal. Alternatively, we provide free doorstep document collection service in all major cities across India."],
  ['home', "What if I don't have all the required documents?", "Our loan experts will review your profile and suggest alternative documents that may be acceptable. We work closely with you to find solutions and ensure a smooth application process."],
  ['home', "What are the current interest rates for different loans?", "Our current rates: Home Loan starting 7.3% p.a., Used Car Loan from 11.5% p.a., Personal Loan from 9.99% p.a., Business Loan from 14% p.a., and Loan Against Property from 9% p.a. Credit Cards offer No Cost EMI + Airport Lounge Access. Rates vary based on your profile and credit score."],
  ['home', "Is the interest rate fixed or floating?", "We offer both fixed and floating rate options. Fixed rates remain constant throughout the tenure, while floating rates change with market conditions. Our advisors help you choose the best option based on your financial goals."],
  ['home', "Are there any hidden charges or processing fees?", "We believe in 100% transparency. Processing fees typically range from 0.5% to 2% of the loan amount. All charges including documentation fees, prepayment charges, and late payment fees are disclosed upfront before you sign."],
  ['home', "How long does the loan approval process take?", "Personal and car loans are typically approved within 24-48 hours. Home loans may take 5-7 working days due to property verification. Business loans usually take 3-5 working days. Express processing is available for urgent requirements."],
  ['home', "Can I prepay my loan without any penalty?", "Most of our partner banks offer zero prepayment charges on floating rate loans. For fixed rate loans, prepayment charges may apply (usually 2-4% of outstanding amount). We recommend floating rate loans for flexibility."],
  ['home', "How do I track my loan application status?", "Once you apply, you receive a unique application ID. You can track your status through our website, mobile app, or by contacting your dedicated relationship manager. We also send SMS and email updates at every stage."],

  // DSA PARTNER PAGE
  ['dsa-partner', "Who can become a Finonest DSA Partner?", "Anyone interested in financial services and business growth. This includes loan agents, ex-bankers, financial advisors, mutual fund agents, chartered accountants, real estate professionals, and business owners."],
  ['dsa-partner', "Is there any registration fee?", "No, Finonest DSA registration is completely investment-free. There are no upfront costs or hidden fees to become a DSA partner."],
  ['dsa-partner', "How do DSAs earn?", "DSAs earn commissions on every successful loan disbursement. Your earnings are directly linked to the loan amount disbursed, giving you unlimited earning potential."],
  ['dsa-partner', "How do I register?", "Simply apply online through Finonest.com on the Become Partner page. Fill the registration form, upload your documents, and our team will contact you for verification and onboarding."],

  // HOME LOAN
  ['home-loan', "What is the minimum credit score required for a home loan?", "A credit score of 650 or above is generally required. However, a score of 750+ gives you access to the best interest rates and faster approvals from our 50+ partner banks."],
  ['home-loan', "How much home loan can I get?", "You can get up to 80-90% of the property value as a home loan. The exact amount depends on your income, credit score, and the property value. We offer loans up to ₹5 Crore."],
  ['home-loan', "What is the maximum tenure for a home loan?", "Home loans can be taken for up to 30 years. A longer tenure means lower EMIs but higher total interest paid. Our advisors help you choose the optimal tenure based on your financial goals."],
  ['home-loan', "Can I get a home loan for an under-construction property?", "Yes, we offer home loans for under-construction properties. The loan is disbursed in stages as construction progresses. You pay only interest (pre-EMI) until full disbursement."],

  // PERSONAL LOAN
  ['personal-loan', "How quickly can I get a personal loan?", "Personal loans are typically approved within 24 hours and disbursed within 24-48 hours of approval. With complete documentation, the entire process can be completed in as little as 24 hours."],
  ['personal-loan', "What is the maximum personal loan amount I can get?", "You can get personal loans up to ₹40 lakhs depending on your income, credit score, and repayment capacity. Salaried individuals with high income and good credit scores qualify for higher amounts."],
  ['personal-loan', "Do I need collateral for a personal loan?", "No, personal loans are unsecured loans. You don't need to pledge any asset or collateral. Approval is based on your income, credit score, and repayment history."],
  ['personal-loan', "Can I foreclose my personal loan early?", "Yes, you can foreclose your personal loan after a lock-in period (usually 6-12 months). Foreclosure charges typically range from 2-5% of the outstanding amount depending on the lender."],

  // CAR LOAN
  ['car-loan', "Can I get 100% financing for a new car?", "Yes, we offer 100% on-road financing for new cars including registration, insurance, and accessories. This means zero down payment for eligible applicants with good credit profiles."],
  ['car-loan', "What is the maximum tenure for a car loan?", "New car loans can be taken for up to 7 years. Used car loans typically have a maximum tenure of 5 years. Longer tenure means lower EMIs but higher total interest."],
  ['car-loan', "How is the car loan interest rate determined?", "Interest rates depend on your credit score, income, loan amount, car model, and the lender. Rates start from 7.99% p.a. for new cars. A higher credit score (750+) gets you the best rates."],
  ['car-loan', "Can I get a car loan for a used car?", "Yes, we offer used car loans for vehicles up to 10 years old at loan maturity. Rates start from 9.50% p.a. with up to 85% financing of the car's value."],

  // USED CAR LOAN
  ['used-car-loan', "What is the maximum age of car eligible for a used car loan?", "The car should not be more than 10 years old at the time of loan maturity. For example, if you take a 5-year loan, the car should currently be no more than 5 years old."],
  ['used-car-loan', "How much loan can I get on a used car?", "You can get up to 90% of the car's current market value as assessed by our partner banks. The exact amount depends on the car's age, condition, make/model, and your credit profile."],
  ['used-car-loan', "What documents are needed for a used car loan?", "You need: Identity proof (Aadhaar/PAN), address proof, income proof (salary slips/ITR), 6 months bank statements, and vehicle documents (RC, insurance, valuation report)."],
  ['used-car-loan', "How fast is the used car loan approval?", "We offer 24-hour approval for used car loans. With complete documentation, you can drive home your car within 1-2 working days of application."],

  // BUSINESS LOAN
  ['business-loan', "What is the minimum business vintage required for a business loan?", "Most lenders require a minimum business vintage of 2 years with filed ITRs. Some NBFCs may consider businesses with 1 year of operations for smaller loan amounts."],
  ['business-loan', "Can I get a collateral-free business loan?", "Yes, we offer collateral-free business loans up to ₹75 lakhs under MSME schemes. For higher amounts, property or business assets may be required as collateral."],
  ['business-loan', "What is the maximum business loan amount available?", "Business loans are available up to ₹5 Crore depending on your business turnover, profitability, and credit history. Working capital loans go up to ₹2 Crore."],
  ['business-loan', "How long does business loan approval take?", "Business loans are typically approved within 3-5 working days after complete documentation. Express processing is available for urgent requirements with additional charges."],

  // LAP
  ['lap', "What percentage of property value can I get as LAP?", "You can get up to 70% of the property's current market value for residential properties, 60% for commercial, 55% for industrial, and 50% for land (with construction)."],
  ['lap', "Can I use LAP funds for any purpose?", "Yes, LAP funds can be used for any legitimate purpose including business expansion, debt consolidation, education, medical treatment, wedding expenses, or working capital needs."],
  ['lap', "What types of properties are accepted for LAP?", "We accept residential properties (apartments, houses, villas), commercial properties (shops, offices, warehouses), industrial properties, and land with construction. The property must have a clear title."],
  ['lap', "What is the maximum tenure for a Loan Against Property?", "LAP can be taken for up to 20 years. The tenure depends on your age, income, and the loan amount. Longer tenure reduces your monthly EMI burden."],

  // CREDIT CARDS
  ['credit-cards', "What credit score is needed to get a credit card?", "Most premium credit cards require a credit score of 700+. However, some entry-level cards are available for scores of 650+. A higher score gives you access to better cards with higher limits."],
  ['credit-cards', "What is No Cost EMI on credit cards?", "No Cost EMI allows you to convert purchases above ₹2,500 into equal monthly installments without any interest. The merchant or bank absorbs the interest cost, making it truly interest-free for you."],
  ['credit-cards', "How many airport lounge visits do I get?", "Complimentary lounge access varies by card — typically 4-8 visits per quarter for premium cards. Travel cards offer unlimited domestic lounge access and select international lounge visits."],
  ['credit-cards', "How long does credit card approval take?", "With complete documentation, credit card approval takes 3-7 working days. Some banks offer instant in-principle approval online, with physical card delivery in 7-10 working days."],
];

$stmt = $db->prepare("INSERT INTO faqs (page, question, answer, sort_order) VALUES (?, ?, ?, ?)");

$pageCounters = [];
$inserted = 0;
$skipped = 0;

foreach ($faqs as $faq) {
    [$page, $question, $answer] = $faq;

    // Check if already exists
    $check = $db->prepare("SELECT id FROM faqs WHERE page = ? AND question = ?");
    $check->execute([$page, $question]);
    if ($check->rowCount() > 0) { $skipped++; continue; }

    $order = $pageCounters[$page] ?? 0;
    $stmt->execute([$page, $question, $answer, $order]);
    $pageCounters[$page] = $order + 1;
    $inserted++;
}

echo json_encode([
    'success' => true,
    'inserted' => $inserted,
    'skipped' => $skipped,
    'message' => "Seed complete. $inserted FAQs inserted, $skipped already existed."
]);
?>
