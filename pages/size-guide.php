<?php
// pages/size-guide.php - Size Guide Page

$page_title = "Size Guide";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get size guide settings from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'size_guide_intro'");
    $stmt->execute();
    $intro_text = $stmt->fetchColumn() ?: "Find the perfect fit with our comprehensive size guide. Measure yourself carefully and compare with our size charts.";
} catch (Exception $e) {
    $intro_text = "Find the perfect fit with our comprehensive size guide. Measure yourself carefully and compare with our size charts.";
}

// Size chart data (can be moved to database later)
$size_charts = [
    'men_clothing' => [
        'title' => "Men's Clothing Size Chart",
        'description' => "Measurements in inches. Find your perfect fit.",
        'chart' => [
            ['Size', 'Chest', 'Waist', 'Hips', 'Sleeve'],
            ['XS', '32-34', '26-28', '32-34', '32'],
            ['S', '34-36', '28-30', '34-36', '33'],
            ['M', '36-38', '30-32', '36-38', '34'],
            ['L', '38-40', '32-34', '38-40', '35'],
            ['XL', '40-42', '34-36', '40-42', '36'],
            ['2XL', '42-44', '36-38', '42-44', '37'],
            ['3XL', '44-46', '38-40', '44-46', '38']
        ]
    ],
    'women_clothing' => [
        'title' => "Women's Clothing Size Chart",
        'description' => "Measurements in inches. Find your perfect fit.",
        'chart' => [
            ['Size', 'Bust', 'Waist', 'Hips', 'Dress Length'],
            ['XS', '30-32', '23-25', '32-34', '36'],
            ['S', '32-34', '25-27', '34-36', '37'],
            ['M', '34-36', '27-29', '36-38', '38'],
            ['L', '36-38', '29-31', '38-40', '39'],
            ['XL', '38-40', '31-33', '40-42', '40'],
            ['2XL', '40-42', '33-35', '42-44', '41'],
            ['3XL', '42-44', '35-37', '44-46', '42']
        ]
    ],
    'men_shoes' => [
        'title' => "Men's Shoe Size Chart",
        'description' => "Convert your foot length to the right shoe size.",
        'chart' => [
            ['UK Size', 'EU Size', 'US Size', 'Foot Length (cm)', 'Foot Length (inches)'],
            ['6', '39', '7', '24.5', '9.6'],
            ['7', '40', '8', '25.5', '10.0'],
            ['8', '41', '9', '26.5', '10.4'],
            ['9', '42', '10', '27.5', '10.8'],
            ['10', '43', '11', '28.5', '11.2'],
            ['11', '44', '12', '29.5', '11.6'],
            ['12', '45', '13', '30.5', '12.0']
        ]
    ],
    'women_shoes' => [
        'title' => "Women's Shoe Size Chart",
        'description' => "Convert your foot length to the right shoe size.",
        'chart' => [
            ['UK Size', 'EU Size', 'US Size', 'Foot Length (cm)', 'Foot Length (inches)'],
            ['3', '36', '5', '22.5', '8.9'],
            ['4', '37', '6', '23.5', '9.3'],
            ['5', '38', '7', '24.5', '9.6'],
            ['6', '39', '8', '25.5', '10.0'],
            ['7', '40', '9', '26.5', '10.4'],
            ['8', '41', '10', '27.5', '10.8'],
            ['9', '42', '11', '28.5', '11.2']
        ]
    ],
    'kids_clothing' => [
        'title' => "Kids' Clothing Size Chart",
        'description' => "Age-based sizing for children. Measurements in inches.",
        'chart' => [
            ['Age', 'Height', 'Chest', 'Waist', 'Weight (lbs)'],
            ['0-3 months', '20-23', '16-17', '16-17', '8-12'],
            ['3-6 months', '23-26', '17-18', '17-18', '12-16'],
            ['6-12 months', '26-29', '18-19', '18-19', '16-22'],
            ['12-18 months', '29-32', '19-20', '19-20', '22-27'],
            ['18-24 months', '32-35', '20-21', '20-21', '27-30'],
            ['2-3 years', '35-38', '21-22', '21-22', '30-34'],
            ['3-4 years', '38-41', '22-23', '22-23', '34-38'],
            ['4-5 years', '41-44', '23-24', '23-24', '38-42']
        ]
    ],
    'jeans' => [
        'title' => "Jeans Size Chart",
        'description' => "Find your perfect jeans size. Waist and inseam measurements in inches.",
        'chart' => [
            ['Size', 'Waist', 'Hips', 'Inseam (Regular)', 'Inseam (Tall)'],
            ['28', '28-29', '34-35', '30', '32'],
            ['29', '29-30', '35-36', '30', '32'],
            ['30', '30-31', '36-37', '30', '32'],
            ['31', '31-32', '37-38', '30', '32'],
            ['32', '32-33', '38-39', '31', '33'],
            ['33', '33-34', '39-40', '31', '33'],
            ['34', '34-35', '40-41', '31', '33'],
            ['36', '36-37', '42-43', '32', '34'],
            ['38', '38-39', '44-45', '32', '34'],
            ['40', '40-41', '46-47', '32', '34']
        ]
    ],
    'rings' => [
        'title' => "Ring Size Chart",
        'description' => "Measure your ring size using circumference or diameter.",
        'chart' => [
            ['US Size', 'Circumference (mm)', 'Diameter (mm)', 'UK Size', 'EU Size'],
            ['3', '44.2', '14.1', 'F', '44'],
            ['4', '46.8', '14.9', 'H', '47'],
            ['5', '49.3', '15.7', 'J', '49'],
            ['6', '51.9', '16.5', 'L', '52'],
            ['7', '54.4', '17.3', 'N', '54'],
            ['8', '57.0', '18.1', 'P', '57'],
            ['9', '59.5', '18.9', 'R', '60'],
            ['10', '62.1', '19.8', 'T', '62'],
            ['11', '64.6', '20.6', 'V', '65'],
            ['12', '67.2', '21.4', 'X', '67']
        ]
    ]
];

// Get active tab from URL
$active_tab = $_GET['tab'] ?? 'men_clothing';
if (!array_key_exists($active_tab, $size_charts)) {
    $active_tab = 'men_clothing';
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Size Guide</h1>
            <p class="header-description"><?= htmlspecialchars($intro_text) ?></p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Size Guide</span>
            </div>
        </div>
    </section>

    <!-- How to Measure -->
    <section class="measure-section">
        <div class="container">
            <h2 class="section-title">How to Measure</h2>
            <div class="measure-grid">
                <div class="measure-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Chest</h3>
                    <p>Measure around the fullest part of your chest, keeping the tape horizontal.</p>
                </div>
                <div class="measure-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Waist</h3>
                    <p>Measure around your natural waistline, keeping the tape snug but not tight.</p>
                </div>
                <div class="measure-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Hips</h3>
                    <p>Stand with feet together and measure around the fullest part of your hips.</p>
                </div>
                <div class="measure-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Inseam</h3>
                    <p>Measure from the crotch seam to the bottom of the leg.</p>
                </div>
                <div class="measure-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Sleeve</h3>
                    <p>Measure from the center back neck to the shoulder and down to the wrist.</p>
                </div>
                <div class="measure-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Foot Length</h3>
                    <p>Stand on a piece of paper and mark the longest point. Measure the distance.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Size Chart Tabs -->
    <section class="size-chart-section">
        <div class="container">
            <div class="tabs-header">
                <button class="tab-btn <?= $active_tab === 'men_clothing' ? 'active' : '' ?>" 
                        onclick="switchTab('men_clothing')">
                    <i class="fas fa-male"></i> Men's Clothing
                </button>
                <button class="tab-btn <?= $active_tab === 'women_clothing' ? 'active' : '' ?>" 
                        onclick="switchTab('women_clothing')">
                    <i class="fas fa-female"></i> Women's Clothing
                </button>
                <button class="tab-btn <?= $active_tab === 'men_shoes' ? 'active' : '' ?>" 
                        onclick="switchTab('men_shoes')">
                    <i class="fas fa-shoe-prints"></i> Men's Shoes
                </button>
                <button class="tab-btn <?= $active_tab === 'women_shoes' ? 'active' : '' ?>" 
                        onclick="switchTab('women_shoes')">
                    <i class="fas fa-shoe-prints"></i> Women's Shoes
                </button>
                <button class="tab-btn <?= $active_tab === 'kids_clothing' ?'active' : '' ?>" 
                        onclick="switchTab('kids_clothing')">
                    <i class="fas fa-child"></i> Kids' Clothing
                </button>
                <button class="tab-btn <?= $active_tab === 'jeans' ? 'active' : '' ?>" 
                        onclick="switchTab('jeans')">
                    <i class="fas fa-tshirt"></i> Jeans
                </button>
                <button class="tab-btn <?= $active_tab === 'rings' ? 'active' : '' ?>" 
                        onclick="switchTab('rings')">
                    <i class="fas fa-ring"></i> Rings
                </button>
            </div>

            <?php foreach ($size_charts as $key => $chart): ?>
                <div class="tab-content <?= $active_tab === $key ? 'active' : '' ?>" id="tab-<?= $key ?>">
                    <div class="size-chart-card">
                        <h2 class="chart-title"><?= htmlspecialchars($chart['title']) ?></h2>
                        <p class="chart-description"><?= htmlspecialchars($chart['description']) ?></p>
                        
                        <div class="table-responsive">
                            <table class="size-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($chart['chart'][0] as $header): ?>
                                            <th><?= htmlspecialchars($header) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i < count($chart['chart']); $i++): ?>
                                        <tr>
                                            <?php foreach ($chart['chart'][$i] as $cell): ?>
                                                <td><?= htmlspecialchars($cell) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="chart-notes">
                            <h4>Notes:</h4>
                            <ul>
                                <li>Measurements are approximate and may vary by brand</li>
                                <li>If you're between sizes, we recommend sizing up</li>
                                <li>Different styles may fit differently - check product descriptions</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- International Conversions -->
    <section class="conversion-section">
        <div class="container">
            <h2 class="section-title">International Size Conversions</h2>
            <div class="conversion-grid">
                <div class="conversion-card">
                    <h3>Clothing</h3>
                    <table class="conversion-table">
                        <tr>
                            <th>US/UK</th>
                            <th>EU</th>
                            <th>FR/ES</th>
                            <th>IT</th>
                        </tr>
                        <tr>
                            <td>XXS</td>
                            <td>32</td>
                            <td>34</td>
                            <td>38</td>
                        </tr>
                        <tr>
                            <td>XS</td>
                            <td>34</td>
                            <td>36</td>
                            <td>40</td>
                        </tr>
                        <tr>
                            <td>S</td>
                            <td>36</td>
                            <td>38</td>
                            <td>42</td>
                        </tr>
                        <tr>
                            <td>M</td>
                            <td>38</td>
                            <td>40</td>
                            <td>44</td>
                        </tr>
                        <tr>
                            <td>L</td>
                            <td>40</td>
                            <td>42</td>
                            <td>46</td>
                        </tr>
                        <tr>
                            <td>XL</td>
                            <td>42</td>
                            <td>44</td>
                            <td>48</td>
                        </tr>
                        <tr>
                            <td>2XL</td>
                            <td>44</td>
                            <td>46</td>
                            <td>50</td>
                        </tr>
                    </table>
                </div>
                
                <div class="conversion-card">
                    <h3>Women's Shoes</h3>
                    <table class="conversion-table">
                        <tr>
                            <th>US</th>
                            <th>UK</th>
                            <th>EU</th>
                            <th>CM</th>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>3</td>
                            <td>36</td>
                            <td>22.5</td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td>4</td>
                            <td>37</td>
                            <td>23.5</td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td>5</td>
                            <td>38</td>
                            <td>24.5</td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td>6</td>
                            <td>39</td>
                            <td>25.5</td>
                        </tr>
                        <tr>
                            <td>9</td>
                            <td>7</td>
                            <td>40</td>
                            <td>26.5</td>
                        </tr>
                    </table>
                </div>

                <div class="conversion-card">
                    <h3>Men's Shoes</h3>
                    <table class="conversion-table">
                        <tr>
                            <th>US</th>
                            <th>UK</th>
                            <th>EU</th>
                            <th>CM</th>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td>6</td>
                            <td>39</td>
                            <td>24.5</td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td>7</td>
                            <td>41</td>
                            <td>26</td>
                        </tr>
                        <tr>
                            <td>9</td>
                            <td>8</td>
                            <td>42</td>
                            <td>27</td>
                        </tr>
                        <tr>
                            <td>10</td>
                            <td>9</td>
                            <td>43</td>
                            <td>28</td>
                        </tr>
                        <tr>
                            <td>11</td>
                            <td>10</td>
                            <td>44</td>
                            <td>29</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Tips Section -->
    <section class="tips-section">
        <div class="container">
            <h2 class="section-title">Fit Tips</h2>
            <div class="tips-grid">
                <div class="tip-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Consider Fabric</h3>
                    <p>Stretchy fabrics like jersey may fit more loosely. Non-stretch fabrics like denim may feel tighter.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Check Reviews</h3>
                    <p>Customer reviews often mention if items run large or small. Use this information to guide your choice.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>When in Doubt, Size Up</h3>
                    <p>If you're between sizes or unsure, we recommend choosing the larger size.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Easy Returns</h3>
                    <p>Don't worry if it doesn't fit perfectly - we offer easy returns and exchanges.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Need Help -->
    <section class="help-section">
        <div class="container">
            <div class="help-card">
                <h2>Still Not Sure About Your Size?</h2>
                <p>Our customer service team is here to help you find the perfect fit.</p>
                <div class="help-options">
                    <a href="<?= BASE_URL ?>pages/contact.php" class="btn-primary">
                        <i class="fas fa-comment"></i>
                        Contact Us
                    </a>
                    <a href="<?= BASE_URL ?>pages/faq.php" class="btn-secondary">
                        <i class="fas fa-question-circle"></i>
                        Visit FAQ
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 4rem 0;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.header-description {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

/* Measure Section */
.measure-section {
    padding: 4rem 0;
    background: white;
}

.section-title {
    font-size: 2rem;
    text-align: center;
    margin-bottom: 3rem;
    position: relative;
    padding-bottom: 1rem;
}

.section-title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 3px;
    background: var(--primary);
}

.measure-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.measure-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
    transition: transform 0.3s;
}

.measure-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.measure-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.measure-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.measure-card p {
    color: var(--text-light);
    line-height: 1.6;
}

/* Size Chart Section */
.size-chart-section {
    padding: 4rem 0;
    background: var(--bg);
}

.tabs-header {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 2rem;
    background: white;
    padding: 0.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-light);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tab-btn:hover {
    color: var(--primary);
    background: var(--bg);
}

.tab-btn.active {
    background: var(--primary);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.size-chart-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.chart-title {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.chart-description {
    color: var(--text-light);
    margin-bottom: 2rem;
}

.table-responsive {
    overflow-x: auto;
    margin-bottom: 2rem;
}

.size-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.size-table th {
    background: var(--primary);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
}

.size-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

.size-table tr:last-child td {
    border-bottom: none;
}

.size-table tr:hover {
    background: var(--bg);
}

.chart-notes {
    background: var(--bg);
    padding: 1.5rem;
    border-radius: 12px;
    border-left: 4px solid var(--primary);
}

.chart-notes h4 {
    font-size: 1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.chart-notes ul {
    list-style: none;
}

.chart-notes li {
    color: var(--text-light);
    margin-bottom: 0.25rem;
    padding-left: 1.5rem;
    position: relative;
}

.chart-notes li:before {
    content: '•';
    position: absolute;
    left: 0.5rem;
    color: var(--primary);
}

/* Conversion Section */
.conversion-section {
    padding: 4rem 0;
    background: white;
}

.conversion-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.conversion-card {
    background: var(--bg);
    padding: 2rem;
    border-radius: 16px;
}

.conversion-card h3 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
    color: var(--text);
    text-align: center;
}

.conversion-table {
    width: 100%;
    border-collapse: collapse;
}

.conversion-table th,
.conversion-table td {
    padding: 0.75rem;
    text-align: center;
    border-bottom: 1px solid var(--border);
}

.conversion-table th {
    background: rgba(59, 130, 246, 0.1);
    color: var(--text);
    font-weight: 600;
}

.conversion-table tr:last-child td {
    border-bottom: none;
}

/* Tips Section */
.tips-section {
    padding: 4rem 0;
    background: var(--bg);
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.tip-card {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.3s;
}

.tip-card:hover {
    transform: translateY(-5px);
}

.tip-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.tip-card h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.tip-card p {
    color: var(--text-light);
    line-height: 1.6;
}

/* Help Section */
.help-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.help-card {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
    color: white;
}

.help-card h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.help-card p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.help-options {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: white;
    color: var(--primary);
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.3);
}

.btn-secondary:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 1200px) {
    .measure-grid,
    .conversion-grid,
    .tips-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .tabs-header {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1 1 calc(33.333% - 0.5rem);
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .measure-grid,
    .conversion-grid,
    .tips-grid {
        grid-template-columns: 1fr;
    }
    
    .tab-btn {
        flex: 1 1 100%;
    }
    
    .help-options {
        flex-direction: column;
    }
    
    .size-chart-card {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .measure-card,
    .conversion-card,
    .tip-card {
        padding: 1.5rem;
    }
}
</style>

<script>
function switchTab(tabId) {
    // Update URL without reloading
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
    
    // Update active tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.tab-btn').classList.add('active');
    
    // Show selected tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById('tab-' + tabId).classList.add('active');
}

// Highlight row on hover
document.querySelectorAll('.size-table tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f8f9fc';
    });
    row.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '';
    });
});

// Add measurement tips tooltips
document.querySelectorAll('.measure-card').forEach(card => {
    card.addEventListener('click', function() {
        const tip = this.querySelector('p').textContent;
        alert('Tip: ' + tip);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>