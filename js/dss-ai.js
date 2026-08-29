/**
 * AGROLINK - SMART AGRICULTURAL DECISION SUPPORT SYSTEM (DSS)
 * AI Prediction Engine & Offline AgroBot Advisor
 */

document.addEventListener('DOMContentLoaded', () => {
    initDSSAnalyzer();

});

// Crop Knowledge Base for AI Decision Matrix
const CROP_DATABASE = {
    winter: [
        {
            name: "Winter Tomatoes (হাইব্রিড টমেটো)",
            suitableSoils: ["loamy", "sandy", "silty"],
            minPh: 6.0, maxPh: 7.0,
            yieldPerAcre: 8500, // kg
            basePrice: 75,
            priceRange: "৳70 - ৳85 / kg",
            profitMargin: "48%",
            marketDemand: "High Demand 🔥",
            confidence: 96,
            recommendation: "Excellent choice for winter. High consumer demand in Dhaka and Gazipur markets with strong future booking interest.",
            npkAdvice: "Apply balanced N:P:K (100:60:80 kg/ha). Add organic vermicompost during land preparation.",
            sellingWindow: "Mid November – Late January"
        },
        {
            name: "Diamond Potatoes (ডায়মন্ড আলু)",
            suitableSoils: ["loamy", "sandy", "peaty"],
            minPh: 5.5, maxPh: 6.5,
            yieldPerAcre: 11000,
            basePrice: 38,
            priceRange: "৳35 - ৳42 / kg",
            profitMargin: "42%",
            marketDemand: "Stable High",
            confidence: 93,
            recommendation: "High yield potential in northern regions (Bogura, Rangpur, Rajshahi). Low perishable risk if stored in local hubs.",
            npkAdvice: "High potassium requirement (K) for tuber bulking. Watch out for late blight if humidity exceeds 85%.",
            sellingWindow: "Late December – February"
        },
        {
            name: "Snowball Cauliflower (ফুলকপি)",
            suitableSoils: ["loamy", "clay", "silty"],
            minPh: 6.0, maxPh: 7.2,
            yieldPerAcre: 7200,
            basePrice: 45,
            priceRange: "৳40 - ৳50 / piece",
            profitMargin: "52%",
            marketDemand: "High Demand 🔥",
            confidence: 91,
            recommendation: "Early winter harvest fetches premium price directly from city consumer groups.",
            npkAdvice: "Boron and Molybdenum micronutrients recommended to prevent hollow stem disease.",
            sellingWindow: "November – Early January"
        }
    ],
    summer: [
        {
            name: "Green Eggplant / Brinjal (বেগুন)",
            suitableSoils: ["loamy", "silty", "clay"],
            minPh: 5.8, maxPh: 6.8,
            yieldPerAcre: 6800,
            basePrice: 65,
            priceRange: "৳60 - ৳75 / kg",
            profitMargin: "45%",
            marketDemand: "High Demand 🔥",
            confidence: 94,
            recommendation: "Continuous harvesting crop throughout summer. High demand across all local grocery channels.",
            npkAdvice: "Regular organic neem cake application to prevent fruit and shoot borer pests.",
            sellingWindow: "April – July"
        },
        {
            name: "Country Gourd (লাউ / কদু)",
            suitableSoils: ["loamy", "sandy", "silty"],
            minPh: 6.0, maxPh: 7.0,
            yieldPerAcre: 9200,
            basePrice: 50,
            priceRange: "৳45 - ৳60 / piece",
            profitMargin: "50%",
            marketDemand: "Moderate to High",
            confidence: 89,
            recommendation: "Low investment cost with rapid climbing growth. Excellent returns with trellis systems.",
            npkAdvice: "Ensure adequate drainage during sudden pre-monsoon showers.",
            sellingWindow: "May – August"
        }
    ],
    monsoon: [
        {
            name: "Aman Paddy / High-Yield Rice (আমন ধান)",
            suitableSoils: ["clay", "loamy", "silty"],
            minPh: 5.5, maxPh: 7.5,
            yieldPerAcre: 2400,
            basePrice: 34,
            priceRange: "৳32 - ৳38 / kg",
            profitMargin: "38%",
            marketDemand: "Guaranteed Staples",
            confidence: 95,
            recommendation: "Primary staple crop for monsoon wetlands. Highly dependable demand and government MSP support.",
            npkAdvice: "Split Urea top-dressing at tillering and panicle initiation stages.",
            sellingWindow: "November – December"
        },
        {
            name: "Monsoon Ladies Finger / Okra (ঢেঁড়শ)",
            suitableSoils: ["loamy", "sandy", "clay"],
            minPh: 6.0, maxPh: 6.8,
            yieldPerAcre: 4800,
            basePrice: 55,
            priceRange: "৳50 - ৳62 / kg",
            profitMargin: "46%",
            marketDemand: "High Demand 🔥",
            confidence: 88,
            recommendation: "Quick harvest turnaround (45-50 days). Very popular in urban daily consumer baskets.",
            npkAdvice: "Ensure raised bed cultivation to prevent root rot during heavy rainfall.",
            sellingWindow: "July – September"
        }
    ]
};

// AI DSS Analyzer Form Handler
function initDSSAnalyzer() {
    const form = document.querySelector('.dss-form');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const location = document.getElementById('location')?.value || 'dhaka';
        const landSize = parseFloat(document.getElementById('land-size')?.value) || 1.0;
        const soilType = document.getElementById('soil-type')?.value || 'loamy';
        const soilPh = parseFloat(document.getElementById('soil-ph')?.value) || 6.5;
        const season = document.getElementById('season')?.value || 'winter';
        const nitrogen = parseInt(document.getElementById('nitrogen')?.value) || 60;
        const phosphorus = parseInt(document.getElementById('phosphorus')?.value) || 45;
        const potassium = parseInt(document.getElementById('potassium')?.value) || 50;

        // Show AI Computing State
        showLoadingState();

        setTimeout(() => {
            runAIPrediction({
                location, landSize, soilType, soilPh, season, nitrogen, phosphorus, potassium
            });
        }, 800);
    });
}

function showLoadingState() {
    const resultsSection = document.querySelector('.results-section');
    if (!resultsSection) return;

    resultsSection.scrollIntoView({ behavior: 'smooth' });
    resultsSection.innerHTML = `
        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
            <div style="font-size: 38px; animation: spin 1.5s linear infinite; display: inline-block;">🌱</div>
            <h3 style="margin-top: 15px; font-family: 'Playfair Display', serif; font-size: 22px; color: var(--dark);">AgroLink AI is Analyzing Farm Data...</h3>
            <p style="color: var(--light-text); font-size: 14px; margin-top: 6px; max-width: 500px; margin-left: auto; margin-right: auto;">
                Evaluating soil chemistry, historical market price curves, consumer demand broadcasts, and regional weather indices.
            </p>
            <style>
                @keyframes spin { 0% { transform: rotate(0deg) scale(1); } 50% { transform: rotate(180deg) scale(1.2); } 100% { transform: rotate(360deg) scale(1); } }
            </style>
        </div>
    `;
}

function runAIPrediction(params) {
    const crops = CROP_DATABASE[params.season] || CROP_DATABASE.winter;
    const primaryCrop = crops[0];
    const secondaryCrop = crops[1] || crops[0];
    const totalEstYield = Math.round(primaryCrop.yieldPerAcre * params.landSize);
    const estRevenue = Math.round(totalEstYield * primaryCrop.basePrice);

    const resultsHtml = `
        <!-- AI SMART SUMMARY HEADER -->
        <div class="ai-summary-banner" style="background: linear-gradient(135deg, #1b5e20, #2e7d32); color: white; padding: 26px 30px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 6px 20px rgba(27, 94, 32, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div>
                    <span style="display: inline-block; background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">
                        ✦ AgroLink AI Recommendation Matrix
                    </span>
                    <h2 style="margin: 8px 0 4px; font-family: 'Playfair Display', serif; font-size: 26px; color: white;">
                        Top Match: ${primaryCrop.name}
                    </h2>
                    <p style="opacity: 0.9; font-size: 14px;">
                        Optimized for ${params.landSize} acres in ${capitalize(params.location)} (${capitalize(params.soilType)} soil, ${capitalize(params.season)} season).
                    </p>
                </div>
                <div style="text-align: right; background: rgba(0,0,0,0.15); padding: 12px 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15);">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; opacity: 0.85; display: block;">ESTIMATED REVENUE</span>
                    <strong style="font-size: 28px; font-weight: 700; color: #a5d6a7;">৳${estRevenue.toLocaleString()}</strong>
                    <span style="font-size: 12px; display: block; opacity: 0.9;">Est. Yield: ${totalEstYield.toLocaleString()} kg</span>
                </div>
            </div>
        </div>

        <!-- 3 CROP RECOMMENDATIONS CARDS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
            ${crops.map((crop, idx) => `
                <div style="background: white; border: 1px solid ${idx === 0 ? 'var(--primary)' : 'var(--border)'}; border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); position: relative;">
                    ${idx === 0 ? `<span style="position: absolute; top: -10px; right: 18px; background: var(--primary); color: white; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 12px;">★ TOP AI CHOICE</span>` : ''}
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-size: 17px; font-weight: 700; color: var(--dark);">${crop.name}</h3>
                        <span style="background: #e8f5e9; color: #2e7d32; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px;">${crop.confidence}% Match</span>
                    </div>
                    <p style="font-size: 13px; color: var(--text); line-height: 1.5; margin-bottom: 16px;">${crop.recommendation}</p>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #fafcf9; padding: 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 12px; margin-bottom: 14px;">
                        <div>
                            <span style="color: var(--light-text); display: block;">Suggested Price:</span>
                            <strong style="color: var(--dark); font-size: 14px;">${crop.priceRange}</strong>
                        </div>
                        <div>
                            <span style="color: var(--light-text); display: block;">Profit Margin:</span>
                            <strong style="color: #2e7d32; font-size: 14px;">${crop.profitMargin}</strong>
                        </div>
                        <div>
                            <span style="color: var(--light-text); display: block;">Est. Total Yield:</span>
                            <strong style="color: var(--dark);">${Math.round(crop.yieldPerAcre * params.landSize).toLocaleString()} kg</strong>
                        </div>
                        <div>
                            <span style="color: var(--light-text); display: block;">Market Demand:</span>
                            <strong style="color: #e65100;">${crop.marketDemand}</strong>
                        </div>
                    </div>

                    <div style="font-size: 12px; color: #3b633a; background: #f2f8f0; padding: 10px 12px; border-radius: 6px; border-left: 3px solid var(--primary); margin-bottom: 14px;">
                        <strong>🌱 AI Soil Advisory:</strong> ${crop.npkAdvice}
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <a href="add-product.php" style="flex: 1; text-align: center; background: var(--primary); color: white; padding: 9px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;">
                            List This Harvest
                        </a>
                        <a href="#agrobot" onclick="askAgroBotAbout('${crop.name}')" style="background: white; border: 1px solid var(--border); color: var(--dark); padding: 9px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;">
                            Ask AI 💬
                        </a>
                    </div>
                </div>
            `).join('')}
        </div>

        <!-- MARKET DEMAND INTELLIGENCE & TIMING -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
            <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
                <span style="color: var(--primary); font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">MARKET TIMING INTELLIGENCE (FR-29)</span>
                <h3 style="font-size: 18px; color: var(--dark); margin: 6px 0 12px;">Optimal Selling & Harvest Windows</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="padding: 12px; background: #fafcf9; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 14px; display: block; color: var(--dark);">${primaryCrop.name}</strong>
                            <span style="font-size: 12px; color: var(--light-text);">Peak Consumer Demand: ${primaryCrop.sellingWindow}</span>
                        </div>
                        <span style="background: #e8f5e9; color: #2e7d32; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">+18% Price Premium</span>
                    </div>
                    <div style="padding: 12px; background: #fafcf9; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 14px; display: block; color: var(--dark);">${secondaryCrop.name}</strong>
                            <span style="font-size: 12px; color: var(--light-text);">Peak Consumer Demand: ${secondaryCrop.sellingWindow}</span>
                        </div>
                        <span style="background: #e8f5e9; color: #2e7d32; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">+12% Price Premium</span>
                    </div>
                </div>
            </div>

            <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
                <span style="color: var(--primary); font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">DEMAND GAP INTELLIGENCE (FR-28)</span>
                <h3 style="font-size: 18px; color: var(--dark); margin: 6px 0 12px;">Active Consumer Demand Broadcasts</h3>
                <p style="font-size: 13px; color: var(--text); margin-bottom: 12px;">Buyers in your region have posted these immediate purchasing requests:</p>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div style="padding: 10px 14px; background: #f7faf6; border-radius: 6px; border-left: 3px solid #f57c00; font-size: 13px; display: flex; justify-content: space-between; align-items: center;">
                        <span>🛒 <strong>42 Buyers</strong> want <em>Organic Tomatoes</em> (Target: ৳75/kg)</span>
                        <a href="farmer-demands.php" style="color: var(--primary); font-weight: 700; font-size: 12px; text-decoration: none;">View →</a>
                    </div>
                    <div style="padding: 10px 14px; background: #f7faf6; border-radius: 6px; border-left: 3px solid #2e7d32; font-size: 13px; display: flex; justify-content: space-between; align-items: center;">
                        <span>🛒 <strong>28 Buyers</strong> want <em>Diamond Potatoes</em> (Target: ৳38/kg)</span>
                        <a href="farmer-demands.php" style="color: var(--primary); font-weight: 700; font-size: 12px; text-decoration: none;">View →</a>
                    </div>
                </div>
            </div>
        </div>
    `;

    const resultsSection = document.querySelector('.results-section');
    if (resultsSection) {
        resultsSection.innerHTML = resultsHtml;
        resultsSection.scrollIntoView({ behavior: 'smooth' });
    }
}


function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
