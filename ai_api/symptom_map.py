# symptom_map.py

SYMPTOM_MAP = {
    # =========================
    # CƠ XƯƠNG KHỚP
    # =========================
    "dau khop": [
        "arthritis", "gout", "osteoarthritis", "rheumatoid arthritis"
    ],
    "sung khop": [
        "arthritis", "gout", "rheumatoid arthritis"
    ],
    "cung khop": [
        "arthritis", "osteoarthritis", "rheumatoid arthritis"
    ],
    "nhuc khop": [
        "arthritis", "gout", "osteoarthritis"
    ],
    "dau co xuong": [
        "arthritis", "muscle strain", "osteoarthritis"
    ],
    "dau nhuc xuong khop": [
        "arthritis", "influenza", "dengue fever"
    ],

    # =========================
    # THẦN KINH
    # =========================
    "dau dau": [
        "headache", "migraine", "hypertension", "influenza"
    ],
    "nhuc dau": [
        "headache", "migraine", "influenza"
    ],
    "dau nua dau": [
        "migraine"
    ],
    "choang vang": [
        "anemia", "hypotension", "vertigo"
    ],
    "chong mat": [
        "vertigo", "hypotension", "anemia", "hypertension"
    ],
    "hoa mat": [
        "vertigo", "hypotension", "anemia"
    ],
    "met dau": [
        "influenza", "anemia", "chronic fatigue syndrome"
    ],
    "mat tri nho": [
        "alzheimer disease"
    ],
    "co giat": [
        "epilepsy"
    ],
    "run tay": [
        "parkinson disease", "anxiety disorder"
    ],
    "te tay": [
        "neuropathy", "stroke"
    ],
    "te chan": [
        "neuropathy", "stroke"
    ],
    "yeu liet": [
        "stroke"
    ],

    # =========================
    # TOÀN THÂN / SỐT
    # =========================
    "sot": [
        "fever", "influenza", "infection", "pneumonia", "bronchitis"
    ],
    "sot cao": [
        "dengue fever", "infection", "influenza", "pneumonia"
    ],
    "lanh run": [
        "infection", "influenza", "pneumonia"
    ],
    "ot lanh": [
        "infection", "influenza", "pneumonia"
    ],
    "met moi": [
        "anemia", "chronic fatigue syndrome", "influenza", "diabetes"
    ],
    "met": [
        "anemia", "chronic fatigue syndrome", "influenza", "diabetes"
    ],
    "u oai": [
        "anemia", "chronic fatigue syndrome", "depression"
    ],
    "kiet suc": [
        "anemia", "influenza", "chronic fatigue syndrome"
    ],
    "sut can": [
        "cancer", "hyperthyroidism", "diabetes", "tuberculosis"
    ],
    "giam can": [
        "cancer", "hyperthyroidism", "diabetes", "tuberculosis"
    ],
    "tang can": [
        "hypothyroidism", "hormone disorder"
    ],
    "chan an": [
        "influenza", "gastritis", "infection", "depression"
    ],
    "mat vi giac": [
        "influenza", "infection"
    ],

    # =========================
    # HÔ HẤP / TAI MŨI HỌNG
    # =========================
    "ho": [
        "bronchitis", "pneumonia", "common cold", "influenza", "pharyngitis"
    ],
    "ho kho": [
        "bronchitis", "common cold", "pharyngitis"
    ],
    "ho co dom": [
        "bronchitis", "pneumonia", "tuberculosis"
    ],
    "ho dam": [
        "bronchitis", "pneumonia", "tuberculosis"
    ],
    "ho ra mau": [
        "tuberculosis", "lung disease", "pneumonia"
    ],
    "kho tho": [
        "asthma", "bronchitis", "pneumonia", "heart failure"
    ],
    "tho gap": [
        "asthma", "bronchitis", "pneumonia"
    ],
    "tuc nguc": [
        "asthma", "angina", "heart disease"
    ],
    "dau nguc": [
        "heart disease", "angina", "pneumonia"
    ],
    "dau hong": [
        "pharyngitis", "tonsillitis", "common cold", "influenza"
    ],
    "rat hong": [
        "pharyngitis", "tonsillitis"
    ],
    "viem hong": [
        "pharyngitis", "tonsillitis"
    ],
    "kho nuot": [
        "pharyngitis", "tonsillitis", "esophageal disorder"
    ],
    "so mui": [
        "common cold", "allergy", "influenza", "sinusitis"
    ],
    "chay nuoc mui": [
        "common cold", "allergy", "influenza", "sinusitis"
    ],
    "nghet mui": [
        "common cold", "sinusitis", "allergy"
    ],
    "hat hoi": [
        "allergy", "common cold", "influenza"
    ],
    "khan tieng": [
        "pharyngitis", "common cold"
    ],
    "mat tieng": [
        "pharyngitis", "common cold"
    ],

    # =========================
    # TIÊU HÓA
    # =========================
    "dau bung": [
        "gastritis", "stomach ulcer", "food poisoning", "diarrhea"
    ],
    "dau da day": [
        "gastritis", "stomach ulcer", "acid reflux"
    ],
    "day hoi": [
        "gastritis", "indigestion", "acid reflux"
    ],
    "chuong bung": [
        "indigestion", "gastritis", "diarrhea"
    ],
    "kho tieu": [
        "indigestion", "gastritis"
    ],
    "o nong": [
        "acid reflux", "gastritis"
    ],
    "trao nguoc": [
        "acid reflux"
    ],
    "buon non": [
        "food poisoning", "gastritis", "migraine", "stomach ulcer"
    ],
    "non": [
        "food poisoning", "gastritis", "infection"
    ],
    "oi": [
        "food poisoning", "infection"
    ],
    "tieu chay": [
        "diarrhea", "food poisoning", "infection"
    ],
    "di ngoai long": [
        "diarrhea", "food poisoning", "infection"
    ],
    "tao bon": [
        "constipation"
    ],
    "dau quan bung": [
        "gastritis", "food poisoning", "diarrhea"
    ],

    # =========================
    # TIẾT NIỆU / NỘI TIẾT
    # =========================
    "tieu nhieu": [
        "diabetes", "urinary tract infection"
    ],
    "khat nuoc": [
        "diabetes"
    ],
    "tieu buot": [
        "urinary tract infection"
    ],
    "tieu rat": [
        "urinary tract infection"
    ],
    "buot tieu": [
        "urinary tract infection"
    ],
    "tieu duong": [
        "diabetes"
    ],

    # =========================
    # TIM MẠCH
    # =========================
    "cao huyet ap": [
        "hypertension"
    ],
    "ha huyet ap": [
        "hypotension"
    ],
    "tim dap nhanh": [
        "arrhythmia", "anxiety disorder", "hyperthyroidism"
    ],
    "danh trong nguc": [
        "arrhythmia", "anxiety disorder"
    ],
    "hoi hop": [
        "arrhythmia", "anxiety disorder"
    ],

    # =========================
    # CƠ / LƯNG
    # =========================
    "dau lung": [
        "spine disorder", "muscle strain", "kidney disease"
    ],
    "dau co": [
        "muscle strain"
    ],
    "moi vai gay": [
        "muscle strain"
    ],

    # =========================
    # DA LIỄU
    # =========================
    "ngua da": [
        "allergy", "dermatitis", "eczema"
    ],
    "noi man do": [
        "allergy", "skin infection", "eczema"
    ],
    "phat ban": [
        "allergy", "dengue fever", "skin infection"
    ],
    "noi do": [
        "allergy", "skin infection", "eczema"
    ],
    "vang da": [
        "liver disease", "hepatitis"
    ],

    # =========================
    # TÂM THẦN / GIẤC NGỦ
    # =========================
    "mat ngu": [
        "insomnia", "anxiety disorder", "depression"
    ],
    "ngu khong duoc": [
        "insomnia", "anxiety disorder", "depression"
    ],
    "lo au": [
        "anxiety disorder"
    ],
    "cang thang": [
        "anxiety disorder", "depression"
    ],
    "tram cam": [
        "depression"
    ],

    # =========================
    # KHÁC
    # =========================
    "dau rang": [
        "dental infection", "tooth decay"
    ],
    "chay mau cam": [
        "blood disorder"
    ],
    "dau mat": [
        "eye infection"
    ],
    "do mat": [
        "eye infection", "allergy"
    ],
}