/**
 * EN: Implements frontend interaction behavior in `js/countries-cities.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/countries-cities.js`.
 */
// DEPRECATED: This file is no longer actively used by the system.
// The system now uses the API endpoint: api/admin/get_countries_cities.php
// which fetches countries and cities from the recruitment_countries database table.
// This file is kept for backward compatibility only and may be removed in future versions.
// To manage countries and cities, use System Settings > Recruitment Countries.

// Comprehensive Countries and Cities Database
// Contains major cities for all countries worldwide
const countriesCities = {
    // Middle East & North Africa
    'Saudi Arabia': ['Riyadh', 'Jeddah', 'Mecca', 'Medina', 'Dammam', 'Khobar', 'Abha', 'Tabuk', 'Buraidah', 'Khamis Mushait', 'Hail', 'Najran', 'Al Jawf', 'Sakaka', 'Arar', 'Jizan', 'Taif', 'Yanbu', 'Khafji', 'Al Bahah', 'Unaizah', 'Ras Tanura', 'Dawadmi', 'Abqaiq', 'Al Majma\'ah', 'Qatif', 'Dhahran', 'Al Kharj', 'Sabya', 'Al Mubarraz'],
    'United Arab Emirates': ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah', 'Umm Al Quwain', 'Al Ain', 'Khor Fakkan', 'Kalba', 'Madinat Zayed', 'Ruwais', 'Jebel Ali', 'Dubai Marina', 'Palm Jumeirah', 'Business Bay', 'Jumeirah', 'Deira', 'Bur Dubai', 'Al Barsha'],
    'Kuwait': ['Kuwait City', 'Farwaniya', 'Hawally', 'Ahmadi', 'Jahra', 'Mubarak Al-Kabeer', 'Salwa', 'Fahaheel', 'Mahboula', 'Abu Halifa', 'Jabriya', 'Salmiya', 'Mangaf', 'Riggae', 'Bayan', 'Surra', 'Dasma', 'Nuzha', 'Sharq', 'Dasman'],
    'Qatar': ['Doha', 'Al Rayyan', 'Al Wakrah', 'Al Khor', 'Dukhan', 'Lusail', 'Mesaieed', 'Al Shamal', 'Umm Salal', 'West Bay', 'Pearl Qatar', 'Aspire Zone', 'Education City', 'Sports City', 'Barwa Village', 'Lusail City', 'The Pearl', 'West Bay Lagoon', 'Al Sadd', 'Al Mirqab'],
    'Bahrain': ['Manama', 'Riffa', 'Muharraq', 'Hamad Town', 'Isa Town', 'Sitra', 'Jidhafs', 'Sanabis', 'Arad', 'Hidd', 'Al Budaiya', 'Al Mahooz', 'Al Seef', 'Karbabad', 'Hoora', 'Adliya', 'Juffair', 'Diplomatic Area', 'Qudaibiya', 'Zinj'],
    'Oman': ['Muscat', 'Salalah', 'Sohar', 'Nizwa', 'Seeb', 'Sur', 'Rustaq', 'Bahla', 'Ibri', 'Khasab', 'Barka', 'Al Buraimi', 'Bidbid', 'Dawqah', 'Dibba', 'Duqm', 'Fanja', 'Ghala', 'Hamriya', 'Ibra'],
    'Jordan': ['Amman', 'Irbid', 'Zarqa', 'Aqaba', 'Mafraq', 'Salt', 'Madaba', 'Jerash', 'Karak', 'Tafilah', 'Ma\'an', 'Ajloun', 'Al Karak', 'Ash Shoubak', 'Dhiban', 'Ghor Al Safi', 'Husn', 'Khilda', 'Khirbet Ghazaleh', 'Maan'],
    'Lebanon': ['Beirut', 'Tripoli', 'Sidon', 'Tyre', 'Byblos', 'Zahle', 'Baalbek', 'Jounieh', 'Nabatieh', 'Batroun', 'Jbail', 'Zgharta', 'Baabda', 'Beit Mery', 'Bhamdoun', 'Bikfaya', 'Broumana', 'Daraya', 'Deir el Qamar', 'Damour'],
    'Israel': ['Jerusalem', 'Tel Aviv', 'Haifa', 'Rishon LeZion', 'Petah Tikva', 'Ashdod', 'Netanya', 'Beer Sheva', 'Bnei Brak', 'Holon', 'Ramat Gan', 'Rehovot', 'Bat Yam', 'Ashkelon', 'Herzliya', 'Kfar Saba', 'Hadera', 'Modiin', 'Lod', 'Nazareth'],
    'Palestine': ['Gaza City', 'East Jerusalem', 'Hebron', 'Nablus', 'Jenin', 'Ramallah', 'Bethlehem', 'Tulkarm', 'Qalqilya', 'Jericho', 'Rafah', 'Khan Yunis', 'Deir al-Balah', 'Beit Lahiya', 'Jabalia', 'Salfit', 'Tubas', 'Yatta', 'Dura', 'Halhul'],
    'Egypt': ['Cairo', 'Alexandria', 'Giza', 'Shubra El Kheima', 'Port Said', 'Suez', 'Luxor', 'Mansoura', 'Tanta', 'Asyut', 'Ismailia', 'Faiyum', 'Zagazig', 'Aswan', 'Damietta', 'Minya', 'Damanhur', 'Beni Suef', 'Qena', 'Sohag'],
    
    // North & South America
    'United States': ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose', 'Austin', 'Jacksonville', 'San Francisco', 'Indianapolis', 'Columbus', 'Fort Worth', 'Charlotte', 'Seattle', 'Denver', 'Washington'],
    'Canada': ['Toronto', 'Montreal', 'Vancouver', 'Calgary', 'Edmonton', 'Ottawa', 'Winnipeg', 'Quebec City', 'Hamilton', 'Kitchener', 'London', 'Victoria', 'Halifax', 'Oshawa', 'Windsor', 'Saskatoon', 'Regina', 'Sherbrooke', 'St. Catharines', 'Barrie'],
    'Mexico': ['Mexico City', 'Guadalajara', 'Monterrey', 'Puebla', 'Tijuana', 'León', 'Juárez', 'Torreón', 'Querétaro', 'San Luis Potosí', 'Mérida', 'Mexicali', 'Aguascalientes', 'Cuernavaca', 'Tepic', 'Chihuahua', 'Durango', 'Cancún', 'Toluca', 'Morelia'],
    'Brazil': ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador', 'Fortaleza', 'Belo Horizonte', 'Manaus', 'Curitiba', 'Recife', 'Porto Alegre', 'Belém', 'Goiânia', 'Guarulhos', 'Campinas', 'São Luís', 'Maceió', 'Duque de Caxias', 'Natal', 'Teresina', 'Campo Grande'],
    'Argentina': ['Buenos Aires', 'Córdoba', 'Rosario', 'Mendoza', 'Tucumán', 'La Plata', 'Mar del Plata', 'Salta', 'Santa Fe', 'San Juan', 'Resistencia', 'Neuquén', 'Corrientes', 'Santiago del Estero', 'Posadas', 'Formosa', 'San Salvador de Jujuy', 'Paraná', 'Bahía Blanca', 'San Fernando del Valle de Catamarca'],
    'Chile': ['Santiago', 'Valparaíso', 'Concepción', 'La Serena', 'Antofagasta', 'Temuco', 'Rancagua', 'Iquique', 'Puerto Montt', 'Arica', 'Talca', 'Chillán', 'Valdivia', 'Copiapó', 'Osorno', 'Los Angeles', 'Curicó', 'Quilpué', 'Punta Arenas', 'Villa Alemana'],
    'Colombia': ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena', 'Cúcuta', 'Soledad', 'Ibagué', 'Bucaramanga', 'Santa Marta', 'Pereira', 'Manizales', 'Villavicencio', 'Armenia', 'Pasto', 'Montería', 'Valledupar', 'Neiva', 'Palmira', 'Buenaventura'],
    'Peru': ['Lima', 'Arequipa', 'Trujillo', 'Chiclayo', 'Piura', 'Iquitos', 'Cusco', 'Chimbote', 'Huancayo', 'Pucallpa', 'Tacna', 'Ica', 'Juliaca', 'Sullana', 'Chincha Alta', 'Cajamarca', 'Puno', 'Tumbes', 'Talara', 'Huánuco'],
    
    // Europe
    'United Kingdom': ['London', 'Birmingham', 'Manchester', 'Glasgow', 'Liverpool', 'Leeds', 'Sheffield', 'Edinburgh', 'Bristol', 'Leicester', 'Coventry', 'Cardiff', 'Belfast', 'Newcastle', 'Nottingham', 'Kingston upon Hull', 'Plymouth', 'Stoke-on-Trent', 'Wolverhampton', 'Derby'],
    'France': ['Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier', 'Bordeaux', 'Lille', 'Rennes', 'Reims', 'Saint-Étienne', 'Le Havre', 'Toulon', 'Angers', 'Grenoble', 'Dijon', 'Nîmes', 'Villeurbanne'],
    'Germany': ['Berlin', 'Hamburg', 'Munich', 'Cologne', 'Frankfurt', 'Stuttgart', 'Düsseldorf', 'Dortmund', 'Essen', 'Leipzig', 'Bremen', 'Dresden', 'Hannover', 'Nuremberg', 'Duisburg', 'Bochum', 'Wuppertal', 'Bielefeld', 'Bonn', 'Münster'],
    'Italy': ['Rome', 'Milan', 'Naples', 'Turin', 'Palermo', 'Genoa', 'Bologna', 'Florence', 'Bari', 'Catania', 'Venice', 'Verona', 'Messina', 'Padua', 'Trieste', 'Brescia', 'Parma', 'Taranto', 'Prato', 'Modena'],
    'Spain': ['Madrid', 'Barcelona', 'Valencia', 'Seville', 'Zaragoza', 'Málaga', 'Murcia', 'Palma', 'Las Palmas', 'Bilbao', 'Alicante', 'Córdoba', 'Valladolid', 'Vigo', 'Gijón', 'Hospitalet de Llobregat', 'A Coruña', 'Granada', 'Vitoria-Gasteiz', 'Elche'],
    'Netherlands': ['Amsterdam', 'Rotterdam', 'The Hague', 'Utrecht', 'Eindhoven', 'Tilburg', 'Groningen', 'Almere', 'Breda', 'Nijmegen', 'Enschede', 'Haarlem', 'Arnhem', 'Zaanstad', 'Amersfoort', 'Apeldoorn', '\'s-Hertogenbosch', 'Hoofddorp', 'Maastricht', 'Leiden'],
    'Belgium': ['Brussels', 'Antwerp', 'Ghent', 'Charleroi', 'Liège', 'Bruges', 'Namur', 'Leuven', 'Mons', 'Aalst', 'Mechelen', 'La Louvière', 'Kortrijk', 'Hasselt', 'Ostend', 'Sint-Niklaas', 'Tournai', 'Roeselare', 'Verviers', 'Mouscron'],
    'Switzerland': ['Zurich', 'Geneva', 'Basel', 'Bern', 'Lausanne', 'St. Gallen', 'Lucerne', 'Lugano', 'Biel', 'Thun', 'Köniz', 'La Chaux-de-Fonds', 'Schaffhausen', 'Rapperswil', 'Yverdon-les-Bains', 'Bellinzona', 'Locarno', 'Ascona', 'Fribourg', 'Neuchâtel'],
    'Austria': ['Vienna', 'Graz', 'Linz', 'Salzburg', 'Innsbruck', 'Klagenfurt', 'Villach', 'Wels', 'Sankt Pölten', 'Dornbirn', 'Wiener Neustadt', 'Steyr', 'Gleisdorf', 'Bregenz', 'Eisenstadt', 'Leonding', 'Traun', 'Ansfelden', 'Bad Vöslau', 'Amstetten'],
    'Poland': ['Warsaw', 'Kraków', 'Łódź', 'Wrocław', 'Poznań', 'Gdańsk', 'Szczecin', 'Bydgoszcz', 'Lublin', 'Katowice', 'Białystok', 'Gdynia', 'Częstochowa', 'Radom', 'Sosnowiec', 'Toruń', 'Kielce', 'Gliwice', 'Zabrze', 'Bytom'],
    'Portugal': ['Lisbon', 'Porto', 'Vila Nova de Gaia', 'Amadora', 'Braga', 'Funchal', 'Coimbra', 'Setúbal', 'Almada', 'Agualva-Cacém', 'Queluz', 'Rio de Mouro', 'Corroios', 'Barreiro', 'Monsanto', 'Cacém', 'Charneca', 'Caparica', 'Paço de Arcos', 'Matosinhos'],
    'Greece': ['Athens', 'Thessaloniki', 'Patras', 'Piraeus', 'Larissa', 'Heraklion', 'Peristeri', 'Kallithea', 'Acharnes', 'Kalamaria', 'Nikaia', 'Glyfada', 'Volos', 'Ioannina', 'Kavala', 'Katerini', 'Trikala', 'Chania', 'Lamia', 'Agrinio'],
    
    // Asia
    'China': ['Beijing', 'Shanghai', 'Guangzhou', 'Shenzhen', 'Tianjin', 'Wuhan', 'Chengdu', 'Nanjing', 'Xi\'an', 'Hangzhou', 'Dongguan', 'Foshan', 'Shenyang', 'Changsha', 'Harbin', 'Zhengzhou', 'Qingdao', 'Dalian', 'Fuzhou', 'Shijiazhuang'],
    'Japan': ['Tokyo', 'Yokohama', 'Osaka', 'Nagoya', 'Sapporo', 'Fukuoka', 'Kobe', 'Kawasaki', 'Kyoto', 'Saitama', 'Hiroshima', 'Sendai', 'Kitakyushu', 'Chiba', 'Shizuoka', 'Sakai', 'Niigata', 'Kumamoto', 'Okayama', 'Kagoshima'],
    'South Korea': ['Seoul', 'Busan', 'Incheon', 'Daegu', 'Daejeon', 'Gwangju', 'Suwon', 'Ulsan', 'Changwon', 'Goyang', 'Seongnam', 'Bucheon', 'Ansan', 'Anyang', 'Jeonju', 'Cheonan', 'Namyangju', 'Hwaseong', 'Cheongju', 'Pohang'],
    'India': ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Surat', 'Lucknow', 'Kanpur', 'Nagpur', 'Visakhapatnam', 'Bhopal', 'Indore', 'Patna', 'Vadodara', 'Ghaziabad', 'Ludhiana'],
    'Indonesia': ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Semarang', 'Makassar', 'Palembang', 'Tangerang', 'Depok', 'Bekasi', 'Batam', 'Pekanbaru', 'Bogor', 'Yogyakarta', 'Malang', 'Padang', 'Banjarmasin', 'Pontianak', 'Samarinda', 'Denpasar'],
    'Malaysia': ['Kuala Lumpur', 'George Town', 'Ipoh', 'Shah Alam', 'Petaling Jaya', 'Klang', 'Johor Bahru', 'Subang Jaya', 'Kuching', 'Kota Kinabalu', 'Seremban', 'Malacca City', 'Alor Setar', 'Kuantan', 'Kangar', 'Putrajaya', 'Labuan', 'Sandakan', 'Tawau', 'Sibu'],
    'Thailand': ['Bangkok', 'Nonthaburi', 'Nakhon Ratchasima', 'Chiang Mai', 'Hat Yai', 'Udon Thani', 'Pak Kret', 'Khon Kaen', 'Ubon Ratchathani', 'Nakhon Si Thammarat', 'Songkhla', 'Surat Thani', 'Phitsanulok', 'Nakhon Pathom', 'Samut Prakan', 'Rayong', 'Chonburi', 'Pattaya', 'Hua Hin', 'Cha-am'],
    'Philippines': ['Manila', 'Quezon City', 'Cebu', 'Davao', 'Caloocan', 'Zamboanga', 'Antipolo', 'Pasig', 'Taguig', 'Valenzuela', 'Cebu City', 'Davao City', 'Parañaque', 'Las Piñas', 'Makati', 'Bacolod', 'Cagayan de Oro', 'San Juan', 'Mandaluyong', 'Marikina'],
    'Vietnam': ['Ho Chi Minh City', 'Hanoi', 'Da Nang', 'Hai Phong', 'Can Tho', 'Bien Hoa', 'Hue', 'Nha Trang', 'Buon Ma Thuot', 'Qui Nhon', 'Vung Tau', 'Haiphong', 'An Giang', 'Ba Ria-Vung Tau', 'Bac Lieu', 'Bac Giang', 'Bac Kan', 'Bac Ninh', 'Ben Tre', 'Binh Dinh'],
    'Singapore': ['Singapore'],
    'Bangladesh': ['Dhaka', 'Chittagong', 'Khulna', 'Sylhet', 'Rajshahi', 'Barisal', 'Rangpur', 'Mymensingh', 'Comilla', 'Narayanganj', 'Savar', 'Jessore', 'Saidpur', 'Bogra', 'Cox\'s Bazar', 'Tongi', 'Dinajpur', 'Jamalpur', 'Sirajganj', 'Faridpur'],
    'Pakistan': ['Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Multan', 'Faisalabad', 'Gujranwala', 'Peshawar', 'Quetta', 'Sialkot', 'Hyderabad', 'Bahawalpur', 'Sargodha', 'Sukkur', 'Larkana', 'Sheikhupura', 'Rahim Yar Khan', 'Jhang', 'Mardan', 'Gujrat'],
    'Afghanistan': ['Kabul', 'Kandahar', 'Herat', 'Mazar-i-Sharif', 'Jalalabad', 'Kunduz', 'Ghazni', 'Balkh', 'Baghlan', 'Gardez', 'Khost', 'Fayzabad', 'Sheberghan', 'Maimana', 'Lashkar Gah', 'Farah', 'Pul-e Khomri', 'Charikar', 'Khulm', 'Mehtar Lam'],
    'Sri Lanka': ['Colombo', 'Dehiwala-Mount Lavinia', 'Moratuwa', 'Negombo', 'Kandy', 'Jaffna', 'Galle', 'Trincomalee', 'Batticaloa', 'Anuradhapura', 'Kurunegala', 'Gampaha', 'Kalutara', 'Ratnapura', 'Badulla', 'Polonnaruwa', 'Hambantota', 'Matale', 'Nuwara Eliya', 'Puttalam'],
    
    // Africa
    'South Africa': ['Cape Town', 'Johannesburg', 'Durban', 'Pretoria', 'Port Elizabeth', 'Bloemfontein', 'East London', 'Welkom', 'Kimberley', 'Polokwane', 'Nelspruit', 'Rustenburg', 'Benoni', 'Vereeniging', 'Vanderbijlpark', 'Centurion', 'Midrand', 'Boksburg', 'Kempton Park', 'Sandton'],
    'Nigeria': ['Lagos', 'Kano', 'Ibadan', 'Benin City', 'Port Harcourt', 'Kaduna', 'Abuja', 'Maiduguri', 'Zaria', 'Aba', 'Jos', 'Ilorin', 'Oyo', 'Auchi', 'Enugu', 'Onitsha', 'Abakaliki', 'Bauchi', 'Akure', 'Abeokuta'],
    'Kenya': ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika', 'Malindi', 'Kitale', 'Garissa', 'Kakamega', 'Nyeri', 'Kericho', 'Embu', 'Meru', 'Wajir', 'Kilifi', 'Kisii', 'Isiolo', 'Lamu', 'Homa Bay'],
    'Ethiopia': ['Addis Ababa', 'Dire Dawa', 'Mekele', 'Gondar', 'Awassa', 'Bahir Dar', 'Dessie', 'Jimma', 'Jijiga', 'Shashamane', 'Arba Minch', 'Kombolcha', 'Debre Markos', 'Weldiya', 'Sodo', 'Asella', 'Nekemte', 'Harar', 'Debre Birhan', 'Adwa'],
    'Ghana': ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Ashaiman', 'Sunyani', 'Techiman', 'Cape Coast', 'Obuasi', 'Teshie', 'Tema', 'Madina', 'Koforidua', 'Wa', 'Ho', 'Bolgatanga', 'Navrongo', 'Dunkwa', 'Konongo', 'Bawku'],
    'Morocco': ['Casablanca', 'Rabat', 'Fes', 'Marrakech', 'Tangier', 'Agadir', 'Meknes', 'Oujda', 'Kenitra', 'Tetouan', 'Safi', 'Mohammedia', 'Beni Mellal', 'El Jadida', 'Taza', 'Nador', 'Settat', 'Larache', 'Ksar El Kebir', 'Guelmim'],
    'Algeria': ['Algiers', 'Oran', 'Constantine', 'Annaba', 'Blida', 'Batna', 'Djelfa', 'Setif', 'Sidi Bel Abbes', 'Biskra', 'Tebessa', 'Tiaret', 'Bejaia', 'Tlemcen', 'Bordj Bou Arreridj', 'Bechar', 'Skikda', 'Souk Ahras', 'Chlef', 'Ghardaia'],
    'Tunisia': ['Tunis', 'Sfax', 'Sousse', 'Kairouan', 'Bizerte', 'Gabes', 'Ariana', 'Kasserine', 'Monastir', 'Ben Arous', 'Medenine', 'Gafsa', 'Jendouba', 'Kebili', 'Tataouine', 'Tozeur', 'Beja', 'Siliana', 'Mahdia', 'Zaghouan'],
    'Libya': ['Tripoli', 'Benghazi', 'Misrata', 'Sabha', 'Zawiya', 'Sirte', 'Tajura', 'Tarhuna', 'Zliten', 'Bani Walid', 'El Bayda', 'Ajdabiya', 'Kufra', 'Zintan', 'Yafran', 'Murzuq', 'Dirj', 'Tazirbu', 'Ghadames', 'Sabratha'],
    'Sudan': ['Khartoum', 'Omdurman', 'Port Sudan', 'Kassala', 'El Geneina', 'El Fasher', 'Nyala', 'Ad Damazin', 'Kosti', 'El Gedaref', 'Wad Medani', 'Shendi', 'Sennar', 'Ed Damer', 'El Obeid', 'Atbara', 'Kadugli', 'Dongola', 'Singa', 'Umm Ruwaba'],
    'Tanzania': ['Dar es Salaam', 'Mwanza', 'Arusha', 'Dodoma', 'Mbeya', 'Morogoro', 'Tanga', 'Zanzibar', 'Kigoma', 'Mtwara', 'Tabora', 'Moshi', 'Iringa', 'Sumbawanga', 'Bukoba', 'Lindi', 'Musoma', 'Kasulu', 'Biharamulo', 'Itigi'],
    'Uganda': ['Kampala', 'Gulu', 'Lira', 'Mbarara', 'Jinja', 'Mbale', 'Mukono', 'Masaka', 'Entebbe', 'Arua', 'Soroti', 'Fort Portal', 'Kasese', 'Iganga', 'Busia', 'Kabale', 'Hoima', 'Tororo', 'Kumi', 'Pallisa'],
    'Zambia': ['Lusaka', 'Kitwe', 'Ndola', 'Kabwe', 'Chingola', 'Mufulira', 'Livingstone', 'Luanshya', 'Kasama', 'Chipata', 'Mazabuka', 'Chililabombwe', 'Mongu', 'Kafue', 'Senanga', 'Choma', 'Mpika', 'Nchelenge', 'Kalulushi', 'Chambishi'],
    'Zimbabwe': ['Harare', 'Bulawayo', 'Chitungwiza', 'Mutare', 'Gweru', 'Epworth', 'Kwekwe', 'Kadoma', 'Masvingo', 'Chinhoyi', 'Marondera', 'Norton', 'Chegutu', 'Bindura', 'Zvishavane', 'Victoria Falls', 'Beitbridge', 'Kariba', 'Karoi', 'Gwanda'],
    'Mozambique': ['Maputo', 'Matola', 'Beira', 'Nampula', 'Chimoio', 'Nacala', 'Quelimane', 'Tete', 'Lichinga', 'Pemba', 'Dondo', 'Inhambane', 'Maxixe', 'Moatize', 'Angoche', 'Cuamba', 'Montepuez', 'Mocuba', 'Manica', 'Gondola'],
    'Madagascar': ['Antananarivo', 'Toamasina', 'Antsirabe', 'Mahajanga', 'Fianarantsoa', 'Toliara', 'Antsiranana', 'Antanifotsy', 'Ambatondrazaka', 'Ambovombe', 'Manjakandriana', 'Sambava', 'Ambositra', 'Mahanoro', 'Vohipeno', 'Morondava', 'Farafangana', 'Maroantsetra', 'Soavinandriana', 'Ambanja'],
    'Cameroon': ['Douala', 'Yaounde', 'Garoua', 'Kousseri', 'Bamenda', 'Maroua', 'Bafoussam', 'Mokolo', 'Nkongsamba', 'Buea', 'Kribi', 'Limbe', 'Edea', 'Bafang', 'Kumbo', 'Kumbo', 'Ngaoundere', 'Bertoua', 'Ebolowa', 'Loum'],
    'Ivory Coast': ['Abidjan', 'Bouake', 'Daloa', 'San-Pedro', 'Korhogo', 'Man', 'Divo', 'Gagnoa', 'Abengourou', 'Anyama', 'Yamoussoukro', 'Grand-Bassam', 'Bingerville', 'Agboville', 'Dabou', 'Katiola', 'Odienne', 'Seguela', 'Bondoukou', 'Daoukro'],
    'Senegal': ['Dakar', 'Touba', 'Thies', 'Rufisque', 'Kaolack', 'Ziguinchor', 'Saint-Louis', 'Diourbel', 'Tambacounda', 'Louga', 'Mbour', 'Richard Toll', 'Kolda', 'Mbacke', 'Tivaouane', 'Dagana', 'Kedougou', 'Fatick', 'Joal-Fadiout', 'Sokone'],
    'Mali': ['Bamako', 'Sikasso', 'Mopti', 'Koutiala', 'Kayes', 'Segou', 'Gao', 'Timbuktu', 'Kidal', 'Kita', 'Bougouni', 'Kati', 'Djenne', 'Nioro', 'San', 'Tombouctou', 'Konna', 'Nara', 'Djenné', 'Taoudenni'],
    'Burkina Faso': ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Banfora', 'Ouahigouya', 'Pouytenga', 'Kaya', 'Tenkodogo', 'Dedougou', 'Fada N\'gourma', 'Dori', 'Gaoua', 'Nouna', 'Réo', 'Houndé', 'Garango', 'Yako', 'Sapouy', 'Koupela', 'Kongoussi'],
    'Niger': ['Niamey', 'Zinder', 'Maradi', 'Agadez', 'Tahoua', 'Dosso', 'Tillaberi', 'Diffa', 'Gaya', 'Arlit', 'Nguigmi', 'Dirkou', 'Ayorou', 'Dogondoutchi', 'Tessaoua', 'Birni N\'Konni', 'Madaoua', 'Illéla', 'Keita', 'Tchirozerine'],
    'Chad': ['N\'Djamena', 'Moundou', 'Sarh', 'Abeche', 'Kelo', 'Am Timan', 'Bongor', 'Mongo', 'Doba', 'Ati', 'Lai', 'Oum Hadjer', 'Bitkine', 'Mao', 'Massaguet', 'Biltine', 'Goz Beida', 'Am Nabak', 'Faya-Largeau', 'Bousso'],
    'Angola': ['Luanda', 'Huambo', 'Lobito', 'Benguela', 'Kuito', 'Lubango', 'Malanje', 'Namibe', 'Soyo', 'Cabinda', 'Uige', 'Tombua', 'Sumbe', 'Menongue', 'Caconda', 'Camacupa', 'Longonjo', 'M\'banza-Kongo', 'Ngiva', 'Saurimo'],
    'Albania': ['Tirana', 'Durrës', 'Vlorë', 'Shkodër', 'Fier', 'Korçë', 'Elbasan', 'Berat', 'Lushnjë', 'Kavajë', 'Gjirokastër', 'Sarandë', 'Pogradec', 'Kukës', 'Lezhë', 'Patos', 'Kucova', 'Burrel', 'Himara', 'Selenica'],
    'Armenia': ['Yerevan', 'Gyumri', 'Vanadzor', 'Vagharshapat', 'Hrazdan', 'Abovyan', 'Kapan', 'Ararat', 'Armavir', 'Stepanavan', 'Goris', 'Artashat', 'Ashtarak', 'Dilijan', 'Spitak', 'Sevan', 'Ijevan', 'Berd', 'Vedi', 'Martuni'],
    'Azerbaijan': ['Baku', 'Ganja', 'Sumqayit', 'Mingachevir', 'Lankaran', 'Shirvan', 'Nakhchivan', 'Shaki', 'Yevlakh', 'Khirdalan', 'Salyan', 'Qaraçuxur', 'Stepanakert', 'Pushkino', 'Bilajari', 'Mastaga', 'Sumgait', 'Qazax', 'Sahil', 'Agdzhabedi'],
    'Belarus': ['Minsk', 'Gomel', 'Mogilev', 'Vitebsk', 'Grodno', 'Brest', 'Bobruisk', 'Baranovichi', 'Borisov', 'Orsha', 'Pinsk', 'Mazyr', 'Salihorsk', 'Novopolotsk', 'Lida', 'Polotsk', 'Maladzyechna', 'Zhytkavichy', 'Navahrudak', 'Smarhon'],
    'Bulgaria': ['Sofia', 'Plovdiv', 'Varna', 'Burgas', 'Ruse', 'Stara Zagora', 'Pleven', 'Sliven', 'Dobrich', 'Shumen', 'Pernik', 'Haskovo', 'Yambol', 'Kazanlak', 'Pazardzhik', 'Kardzhali', 'Vidin', 'Veliko Tarnovo', 'Montana', 'Gabrovo'],
    'Croatia': ['Zagreb', 'Split', 'Rijeka', 'Osijek', 'Zadar', 'Slavonski Brod', 'Pula', 'Karlovac', 'Sisak', 'Dubrovnik', 'Velika Gorica', 'Varaždin', 'Šibenik', 'Bjelovar', 'Kaštela', 'Sesvete', 'Samobor', 'Vinkovci', 'Koprivnica', 'Čakovec'],
    'Czech Republic': ['Prague', 'Brno', 'Ostrava', 'Plzeň', 'Liberec', 'Olomouc', 'Budějovice', 'Hradec Králové', 'Ústí nad Labem', 'Pardubice', 'Zlín', 'Havířov', 'Opava', 'Frýdek-Místek', 'Jihlava', 'Karlovy Vary', 'Teplice', 'Kladno', 'Most', 'Česká Lípa'],
    'Denmark': ['Copenhagen', 'Aarhus', 'Odense', 'Aalborg', 'Esbjerg', 'Randers', 'Kolding', 'Horsens', 'Vejle', 'Roskilde', 'Herning', 'Helsingør', 'Silkeborg', 'Næstved', 'Fredericia', 'Viborg', 'Køge', 'Holstebro', 'Taastrup', 'Svendborg'],
    'Estonia': ['Tallinn', 'Tartu', 'Narva', 'Pärnu', 'Kohtla-Järve', 'Viljandi', 'Rakvere', 'Sillamäe', 'Maardu', 'Kuressaare', 'Võru', 'Jõhvi', 'Haapsalu', 'Keila', 'Narva-Jõesuu', 'Valga', 'Paide', 'Kärdla', 'Saue', 'Türi'],
    'Finland': ['Helsinki', 'Espoo', 'Tampere', 'Vantaa', 'Turku', 'Oulu', 'Jyväskylä', 'Lahti', 'Kuopio', 'Pori', 'Joensuu', 'Lappeenranta', 'Hämeenlinna', 'Vaasa', 'Seinäjoki', 'Rovaniemi', 'Mikkeli', 'Kotka', 'Salo', 'Porvoo'],
    'Georgia': ['Tbilisi', 'Batumi', 'Kutaisi', 'Rustavi', 'Gori', 'Zugdidi', 'Poti', 'Khashuri', 'Samtredia', 'Senaki', 'Telavi', 'Akhaltsikhe', 'Ozurgeti', 'Kaspi', 'Chiatura', 'Tsqaltubo', 'Sagarejo', 'Gardabani', 'Borjomi', 'Tskhinvali'],
    'Hungary': ['Budapest', 'Debrecen', 'Szeged', 'Miskolc', 'Pécs', 'Győr', 'Nyíregyháza', 'Kecskemét', 'Székesfehérvár', 'Szombathely', 'Szolnok', 'Tatabánya', 'Kaposvár', 'Békéscsaba', 'Veszprém', 'Zalaegerszeg', 'Sopron', 'Érd', 'Hódmezővásárhely', 'Dunaújváros'],
    'Iceland': ['Reykjavík', 'Kópavogur', 'Hafnarfjörður', 'Akureyri', 'Reykjanesbær', 'Garðabær', 'Mosfellsbær', 'Árborg', 'Akranes', 'Fjarðabyggð', 'Selfoss', 'Fljótsdalshérað', 'Ísafjörður', 'Sauðárkrókur', 'Borgarnes', 'Dalvík', 'Ólafsvík', 'Reyðarfjörður', 'Stykkishólmur', 'Siglufjörður'],
    'Ireland': ['Dublin', 'Cork', 'Limerick', 'Galway', 'Waterford', 'Drogheda', 'Swords', 'Dundalk', 'Bray', 'Navan', 'Ennis', 'Kilkenny', 'Carlow', 'Tralee', 'Newbridge', 'Naas', 'Athlone', 'Portlaoise', 'Longford', 'Wexford'],
    'Kazakhstan': ['Almaty', 'Nur-Sultan', 'Shymkent', 'Aktobe', 'Taraz', 'Pavlodar', 'Semey', 'Oskemen', 'Oral', 'Atyrau', 'Qaraghandy', 'Temirtau', 'Aqtobe', 'Qostanay', 'Petropavl', 'Qyzylorda', 'Turkestan', 'Kyzylorda', 'Zhanaozen', 'Karagandy'],
    'Kyrgyzstan': ['Bishkek', 'Osh', 'Jalal-Abad', 'Karakol', 'Tokmok', 'Uzgen', 'Balykchy', 'Kara-Balta', 'Naryn', 'Talas', 'Kant', 'Balykchy', 'Kara-Suu', 'Isfana', 'Kyzyl-Kiya', 'Nookat', 'Bazar-Korgon', 'Suluktu', 'Cholpon-Ata', 'Karakol'],
    'Latvia': ['Riga', 'Daugavpils', 'Liepāja', 'Jelgava', 'Jūrmala', 'Ventspils', 'Rēzekne', 'Valmiera', 'Jēkabpils', 'Aizkraukle', 'Ogre', 'Tukums', 'Cēsis', 'Salaspils', 'Kuldīga', 'Olaine', 'Dobele', 'Talsi', 'Ludza', 'Sigulda'],
    'Lithuania': ['Vilnius', 'Kaunas', 'Klaipėda', 'Šiauliai', 'Panevėžys', 'Alytus', 'Marijampolė', 'Mažeikiai', 'Jonava', 'Utena', 'Kėdainiai', 'Telšiai', 'Visaginas', 'Tauragė', 'Ukmergė', 'Plungė', 'Šiauliai', 'Kretinga', 'Radviliškis', 'Druskininkai'],
    'Moldova': ['Chisinau', 'Tiraspol', 'Balti', 'Bender', 'Rîbnița', 'Cahul', 'Ungheni', 'Soroca', 'Orhei', 'Comrat', 'Edineț', 'Ceadîr-Lunga', 'Căușeni', 'Strășeni', 'Drochia', 'Slobozia', 'Florești', 'Vulcănești', 'Chișinău', 'Taraclia'],
    'North Macedonia': ['Skopje', 'Bitola', 'Kumanovo', 'Prilep', 'Tetovo', 'Veles', 'Ohrid', 'Stip', 'Kavadarci', 'Gostivar', 'Strumica', 'Kochani', 'Struga', 'Radovish', 'Gevgelija', 'Debar', 'Kratovo', 'Kriva Palanka', 'Sveti Nikole', 'Vinica'],
    'Norway': ['Oslo', 'Bergen', 'Trondheim', 'Stavanger', 'Bærum', 'Kristiansand', 'Fredrikstad', 'Tromsø', 'Drammen', 'Skien', 'Ålesund', 'Sandnes', 'Haugesund', 'Moss', 'Arendal', 'Bodø', 'Tonsberg', 'Kristiansund', 'Molde', 'Hamar'],
    'Romania': ['Bucharest', 'Cluj-Napoca', 'Timișoara', 'Iași', 'Constanța', 'Craiova', 'Galați', 'Ploiești', 'Brașov', 'Brăila', 'Oradea', 'Arad', 'Pitești', 'Sibiu', 'Bacău', 'Târgu Mureș', 'Baia Mare', 'Buzău', 'Botoșani', 'Satu Mare'],
    'Russia': ['Moscow', 'Saint Petersburg', 'Novosibirsk', 'Yekaterinburg', 'Nizhny Novgorod', 'Kazan', 'Chelyabinsk', 'Omsk', 'Samara', 'Rostov-on-Don', 'Ufa', 'Krasnoyarsk', 'Voronezh', 'Perm', 'Volgograd', 'Krasnodar', 'Saratov', 'Tyumen', 'Tolyatti', 'Izhevsk'],
    'Serbia': ['Belgrade', 'Novi Sad', 'Niš', 'Kragujevac', 'Subotica', 'Zrenjanin', 'Pančevo', 'Čačak', 'Novi Pazar', 'Kraljevo', 'Leskovac', 'Smederevo', 'Užice', 'Vranje', 'Valjevo', 'Šabac', 'Sombor', 'Požarevac', 'Pirot', 'Zaječar'],
    'Slovakia': ['Bratislava', 'Košice', 'Prešov', 'Žilina', 'Nitra', 'Banská Bystrica', 'Trnava', 'Trenčín', 'Martin', 'Poprad', 'Prievidza', 'Zvolen', 'Považská Bystrica', 'Michalovce', 'Nové Zámky', 'Spišská Nová Ves', 'Komárno', 'Levice', 'Humenné', 'Bardejov'],
    'Slovenia': ['Ljubljana', 'Maribor', 'Celje', 'Kranj', 'Velenje', 'Koper', 'Novo Mesto', 'Ptuj', 'Trbovlje', 'Kamnik', 'Jesenice', 'Nova Gorica', 'Murska Sobota', 'Škofja Loka', 'Domžale', 'Izola', 'Kočevje', 'Postojna', 'Logatec', 'Ajdovščina'],
    'Sweden': ['Stockholm', 'Gothenburg', 'Malmö', 'Uppsala', 'Västerås', 'Örebro', 'Linköping', 'Helsingborg', 'Jönköping', 'Norrköping', 'Lund', 'Umeå', 'Gävle', 'Borås', 'Södertälje', 'Eskilstuna', 'Karlstad', 'Täby', 'Trollhättan', 'Luleå'],
    'Ukraine': ['Kiev', 'Kharkiv', 'Odesa', 'Dnipro', 'Donetsk', 'Zaporizhzhya', 'Lviv', 'Kryvyi Rih', 'Mykolaiv', 'Mariupol', 'Luhansk', 'Makiivka', 'Vinnytsia', 'Simferopol', 'Sevastopol', 'Kherson', 'Poltava', 'Chernihiv', 'Cherkasy', 'Sumy'],
    'Uzbekistan': ['Tashkent', 'Samarkand', 'Namangan', 'Andijan', 'Bukhara', 'Nukus', 'Qarshi', 'Fergana', 'Jizzakh', 'Kokand', 'Margilan', 'Navoiy', 'Angren', 'Termez', 'Urgench', 'Guliston', 'Kattaqo\'rg\'on', 'Denov', 'Chirchiq', 'Olmaliq'],
    'Turkey': ['Istanbul', 'Ankara', 'İzmir', 'Bursa', 'Antalya', 'Adana', 'Konya', 'Gaziantep', 'Mersin', 'Diyarbakır', 'Kayseri', 'Şanlıurfa', 'Samsun', 'Manisa', 'Kahramanmaraş', 'Van', 'Malatya', 'Erzurum', 'Batman', 'Elazığ'],
    
    // Oceania
    'Australia': ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Newcastle', 'Canberra', 'Sunshine Coast', 'Wollongong', 'Hobart', 'Geelong', 'Townsville', 'Cairns', 'Darwin', 'Toowoomba', 'Ballarat', 'Bendigo', 'Albury', 'Launceston'],
    'New Zealand': ['Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'Tauranga', 'Napier', 'Palmerston North', 'Dunedin', 'Rotorua', 'New Plymouth', 'Whangarei', 'Invercargill', 'Nelson', 'Hastings', 'Gisborne', 'Timaru', 'Blenheim', 'Taupo', 'Masterton', 'Levin'],
    'Papua New Guinea': ['Port Moresby', 'Lae', 'Arawa', 'Mount Hagen', 'Popondetta', 'Madang', 'Kokopo', 'Mendi', 'Kimbe', 'Goroka', 'Wewak', 'Bulolo', 'Alotau', 'Daru', 'Kavieng', 'Kundiawa', 'Vanimo', 'Rabaul', 'Kerema', 'Lorengau'],
    'Fiji': ['Suva', 'Lautoka', 'Nadi', 'Labasa', 'Ba', 'Sigatoka', 'Savusavu', 'Levuka', 'Nasinu', 'Nasinu', 'Nausori', 'Tavua', 'Rakiraki', 'Korovou', 'Seaqaqa', 'Malawai', 'Wailailai', 'Navua', 'Bua', 'Taveuni'],
    'Samoa': ['Apia', 'Asau', 'Mulifanua', 'Afega', 'Siusega', 'Falefa', 'Vailima', 'Leulumoega', 'Nofoalii', 'Vailele', 'Safotu', 'Faleula', 'Lalovi', 'Sapapalii', 'Fa\'aa', 'Sataoa', 'Letogo', 'Mulifanua', 'Solosolo', 'Magia'],
    'Tonga': ['Nuku\'alofa', 'Neiafu', 'Haveluloto', 'Vaini', 'Pangai', 'Ha\'apai', 'Mu\'a', 'Ohonua', 'Hihifo', 'Kolonga', 'Fahefa', 'Fatai', 'Lapaha', 'Fasi', 'Pakala', 'Nukuleka', 'Fatumu', 'Niutoua', 'Foa', 'Koloa']
};

// Export for module compatibility
if (typeof module !== 'undefined' && module.exports) {
    module.exports = countriesCities;
}

// Attach to browser global (window) so other scripts can access it
// This must be done immediately and explicitly
// STATIC_COUNTRIES_CITIES is never overwritten - use this for autocomplete so we always have all countries/cities
(function() {
    if (typeof window !== 'undefined') {
        window.countriesCities = countriesCities;
        window.STATIC_COUNTRIES_CITIES = countriesCities;
    }
})();