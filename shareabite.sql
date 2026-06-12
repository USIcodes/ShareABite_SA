-- ============================================================
-- ShareABite SA Database
-- ============================================================

DROP DATABASE IF EXISTS shareabite_sa;
CREATE DATABASE shareabite_sa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
 
USE shareabite_sa;
 
-- ============================================================
-- TABLE 1: categories
-- ============================================================
CREATE TABLE categories (
  categoryID    INT          NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80)  NOT NULL,
  description   TEXT,
  createdAt     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (categoryID)
);
 
-- ============================================================
-- TABLE 2: users
-- payshapNumber = seller's PayShap-registered phone number
-- ============================================================
CREATE TABLE users (
  userID          INT           NOT NULL AUTO_INCREMENT,
  firstName       VARCHAR(60)   NOT NULL,
  lastName        VARCHAR(60)   NOT NULL,
  email           VARCHAR(120)  NOT NULL,
  passwordHash    VARCHAR(255)  NOT NULL,
  phone           VARCHAR(20),
  location        VARCHAR(120),
  userType        ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  businessName    VARCHAR(120),
  sellerRating    DECIMAL(3,2)  DEFAULT 0.00,
  preferredArea   VARCHAR(120),
  adminLevel      TINYINT       DEFAULT NULL,
  payshapNumber   VARCHAR(20)   DEFAULT NULL,
  twoFactorSecret  VARCHAR(64)   DEFAULT NULL,
  twoFactorEnabled TINYINT(1)    NOT NULL DEFAULT 0,
  lastLogin       DATETIME,
  createdAt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (userID),
  UNIQUE KEY uq_email (email)
);
 
-- ============================================================
-- TABLE 3: food_listings
-- ============================================================
CREATE TABLE food_listings (
  listingID       INT           NOT NULL AUTO_INCREMENT,
  sellerID        INT           NOT NULL,
  categoryID      INT           NOT NULL,
  title           VARCHAR(150)  NOT NULL,
  description     TEXT          NOT NULL,
  price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  isDonation      TINYINT(1)    NOT NULL DEFAULT 0,
  quantity        INT           NOT NULL DEFAULT 1,
  imageURL        VARCHAR(255)  NOT NULL,
  pickupLocation  VARCHAR(200),
  pickupMethod    ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
  status          ENUM('active','sold','expired','removed') NOT NULL DEFAULT 'active',
  createdAt       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expiresAt       DATETIME,
  PRIMARY KEY (listingID),
  FOREIGN KEY (sellerID)   REFERENCES users(userID)          ON DELETE CASCADE,
  FOREIGN KEY (categoryID) REFERENCES categories(categoryID) ON DELETE RESTRICT
);
 
-- ============================================================
-- TABLE 4: orders
-- ============================================================
CREATE TABLE orders (
  orderID         INT           NOT NULL AUTO_INCREMENT,
  buyerID         INT           NOT NULL,
  listingID       INT           NOT NULL,
  quantity        INT           NOT NULL DEFAULT 1,
  totalAmount     DECIMAL(10,2) NOT NULL,
  deliveryFee     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  pickupMethod    ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
  status          ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  orderDate       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt       DATETIME      ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (orderID),
  FOREIGN KEY (buyerID)   REFERENCES users(userID)              ON DELETE RESTRICT,
  FOREIGN KEY (listingID) REFERENCES food_listings(listingID)   ON DELETE RESTRICT
);
 
-- ============================================================
-- TABLE 5: payments
-- sellerPayshap = seller's PayShap number at time of transaction
-- platformFee   = ShareABite's 5% cut
-- sellerPayout  = amount seller actually receives
-- ============================================================
CREATE TABLE payments (
  paymentID       INT           NOT NULL AUTO_INCREMENT,
  orderID         INT           NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  platformFee     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sellerPayout    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  method          ENUM('payshap','payfast','card','eft','donation') NOT NULL,
  status          ENUM('pending','successful','failed','refunded') NOT NULL DEFAULT 'pending',
  transactionRef  VARCHAR(100),
  buyerPayshap    VARCHAR(20),
  sellerPayshap   VARCHAR(20),
  paymentDate     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (paymentID),
  UNIQUE KEY uq_order_payment (orderID),
  FOREIGN KEY (orderID) REFERENCES orders(orderID) ON DELETE CASCADE
);
 
-- ============================================================
-- TABLE 6: reviews
-- ============================================================
CREATE TABLE reviews (
  reviewID        INT       NOT NULL AUTO_INCREMENT,
  reviewerID      INT       NOT NULL,
  listingID       INT       NOT NULL,
  rating          TINYINT   NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment         TEXT,
  createdAt       DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reviewID),
  FOREIGN KEY (reviewerID) REFERENCES users(userID)              ON DELETE CASCADE,
  FOREIGN KEY (listingID)  REFERENCES food_listings(listingID)   ON DELETE CASCADE
);
 
-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_listings_seller   ON food_listings(sellerID);
CREATE INDEX idx_listings_category ON food_listings(categoryID);
CREATE INDEX idx_listings_status   ON food_listings(status);
CREATE INDEX idx_orders_buyer      ON orders(buyerID);
CREATE INDEX idx_orders_listing    ON orders(listingID);
CREATE INDEX idx_reviews_listing   ON reviews(listingID);
 
-- ============================================================
-- SAMPLE DATA
-- ============================================================
 
INSERT INTO categories (name, description) VALUES
  ('Homemade Meals',    'Freshly cooked meals prepared at home'),
  ('Baked Goods',       'Bread, cakes, and pastries'),
  ('Fresh Produce',     'Vegetables, fruit, and raw ingredients'),
  ('Surplus Groceries', 'Unopened packaged food nearing best-before date'),
  ('Donations',         'Free food listed for community members in need');
 
-- Passwords are bcrypt of "Password1!"
-- payshapNumber is the seller's PayShap-registered phone number
INSERT INTO users (firstName, lastName, email, passwordHash, phone, location, userType, businessName, sellerRating, adminLevel, payshapNumber) VALUES
  ('Admin',   'User',    'admin@shareabite.co.za',  '$2y$10$XUNy0bUAeVRVJI26GXF2ieGLZZrqqEAsXa8ygmMnKKC.zzNr3AaWq', '0110000001', 'Johannesburg', 'admin',  NULL,               0.00, 1,    NULL),
  ('Thabo',   'Mokoena', 'thabo@example.co.za',     '$2y$10$XUNy0bUAeVRVJI26GXF2ieGLZZrqqEAsXa8ygmMnKKC.zzNr3AaWq', '0821234567', 'Soweto',       'seller', 'Thabo''s Kitchen',  4.50, NULL, '0821234567'),
  ('Nomvula', 'Dlamini', 'nomvula@example.co.za',   '$2y$10$XUNy0bUAeVRVJI26GXF2ieGLZZrqqEAsXa8ygmMnKKC.zzNr3AaWq', '0839876543', 'Alexandra',    'buyer',  NULL,               0.00, NULL, NULL);
 
-- imageURL values match the image files in this project folder
INSERT INTO food_listings (sellerID, categoryID, title, description, price, isDonation, quantity, imageURL, pickupLocation, pickupMethod, status) VALUES
  (2, 1, 'Pap and Chakalaka',     'Home-cooked pap with spicy chakalaka. Serves 2. Made fresh this morning with love.',          35.00, 0, 5, '/Shareabite/pap_chakalaka.jpeg',   'Soweto, Orlando East', 'pickup',   'active'),
  (2, 2, 'Freshly Baked Vetkoek', 'Six large vetkoek stuffed with savoury mince filling. Great for lunch or a snack.',           50.00, 0, 6, '/Shareabite/vetkoek_mince.jpeg',   'Soweto, Orlando East', 'delivery', 'active'),
  (2, 5, 'Leftover Bread Loaves', 'Two full bread loaves baked this morning. Still soft and fresh - free for anyone who needs.', 0.00,  1, 2, '/Shareabite/bread_loaves.jpeg',    'Soweto, Orlando East', 'pickup',   'active'),
  (2, 3, 'Tomatoes and Onions',   'Home garden fresh tomatoes and onions, 1kg bag. Picked today.',                               20.00, 0, 3, '/Shareabite/tomatoes_onions.jpeg', 'Soweto, Orlando East', 'pickup',   'active');
 
INSERT INTO orders (buyerID, listingID, quantity, totalAmount, deliveryFee, pickupMethod, status) VALUES
  (3, 1, 2, 70.00, 0.00,  'pickup',   'completed'),
  (3, 2, 1, 75.00, 25.00, 'delivery', 'pending');
 
-- platformFee = 5% of subtotal, sellerPayout = subtotal - platformFee
INSERT INTO payments (orderID, amount, platformFee, sellerPayout, method, status, transactionRef, buyerPayshap, sellerPayshap) VALUES
  (1, 70.00, 3.50, 66.50, 'payshap', 'successful', 'PS-20250601120000001', '0839876543', '0821234567');
 
INSERT INTO reviews (reviewerID, listingID, rating, comment) VALUES
  (3, 1, 5, 'Absolutely delicious! The chakalaka had great flavour. Will order again.');
