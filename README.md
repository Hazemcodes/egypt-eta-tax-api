Egypt ETA Tax API Integration (Laravel/PHP)
  A helper class for integrating with Egypt's Electronic Tax Authority (ETA) system. Handles receipt serialization, hashing (SHA-256), and API submission for e-invoicing compliance.

📌 Features

  •	ETA-Compliant Receipt Serialization (SHA-256 hashing).

  •	OAuth2 Token Authentication for ETA API.

  •	Pre-production API Integration (test environment).

  •	Laravel-ready but works in any PHP project.
  
⚙️ Setup & Usage
  1. Install (Laravel)

    Place ETAController.php in App\Http\Controllers\ (or adjust namespace).

2. Required Environment Variables

    Add these to your .env file:
  
        ETA_CLIENT_ID=your_client_id          	# Provided by ETA  
    
        ETA_CLIENT_SECRET=your_client_secret  	# Provided by ETA  
    
        ETA_POS_SERIAL=your_pos_serial        	# Device serial registered with ETA

   
3. How to Get ETA Credentials

    1.	Register on ETA's Developer Portal.
  
    2.	Apply for pre-production credentials (Sandbox access).
  
    3.	Use the provided client_id, client_secret, and POS serial.


  
4. Usage Example

    Call the sendReceiptToETA() method with the invoice amount:

        use App\Http\Controllers\V1\ETAController;
      
        $eta = new ETAController();
      
        $response = $eta->sendReceiptToETA(1000); // Amount in EGP 
   

5. Customizing Receipt Data
    Modify the $receiptData JSON in sendReceiptToETA() to match your business details:

    •	Seller RIN (Tax Registration Number).

    •	Company address, activity code, etc.

    •	Item details (description, price, etc.).



⚠️ Important Notes

  •	Pre-production only: Replace API endpoints for live use.

  •	Test thoroughly before deploying to production.

  •	ETA’s API docs: Official Documentation.(https://sdk.invoicing.eta.gov.eg/)

