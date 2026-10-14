-- MCKL Mart — public demo database
-- Derived from the project's MariaDB 10.4 schema.
-- Contains NO customer, user, order, or contact-message rows.
-- Import schema.sql first, then seed.sql, into an EMPTY database named mckl_mart_portfolio.
-- For academic demonstration only; not a production commerce service.

SET NAMES utf8mb4;


INSERT INTO `categories` (`category_id`, `name`, `slug`, `description`) VALUES
(1, 'Drinks', 'drinks', 'Chilled beverages, juices, tea and coffee'),
(2, 'Snacks', 'snacks', 'Chips, chocolates, crackers and trail mixes'),
(3, 'Meals', 'meals', 'Ready-to-eat sandwiches, rice bowls and noodles'),
(4, 'Essentials', 'essentials', 'Daily-need items and personal care products');

INSERT INTO `products` (`product_id`, `category_id`, `name`, `price`, `image`, `badge`, `weight`, `calories`, `description`, `stock`, `is_featured`, `created_at`) VALUES
(1, 1, 'Mineral Water 500ml', 1.50, 'Products/Mckl Bottle.webp', NULL, '500ml', '0 kcal', 'Pure filtered mineral water, perfectly chilled. Stay hydrated throughout your day - zero calories, zero sugar.', 91, 0, '2026-06-27 08:16:37'),
(2, 1, 'Chilled Green Tea', 3.50, 'Products/Mckl Green Tea.webp', 'Bestseller', '500ml', '45 kcal', 'Japanese-style green tea lightly sweetened with natural cane sugar. Packed with antioxidants and a smooth flavour.', 70, 1, '2026-06-27 08:16:37'),
(3, 1, 'Fresh Orange Juice', 4.20, 'Products/Mckl Orange Juice.webp', NULL, '350ml', '160 kcal', 'Freshly squeezed orange juice with pulp. Rich in Vitamin C and natural fruit sugars - a great morning pick-me-up.', 0, 0, '2026-06-27 08:16:37'),
(4, 1, 'Iced Coffee Latte', 5.90, 'Products/Mckl Ice Coffee Latee.webp', 'Popular', '240ml', '120 kcal', 'Premium Arabica coffee blended with milk and ice. Smooth, bold, and refreshing - the perfect afternoon energy boost.', 48, 0, '2026-06-27 08:16:37'),
(5, 2, 'Salted Potato Chips', 4.90, 'Products/Mckl Potato Chips.webp', 'Popular', '80g', '380 kcal', 'Crispy golden potato chips seasoned with sea salt. Perfectly thin-cut and baked to a satisfying crunch.', 93, 1, '2026-06-27 08:16:37'),
(6, 2, 'Dark Chocolate Bar', 5.50, 'Products/Mckl Dark choclate.webp', NULL, '50g', '270 kcal', '70% dark chocolate with a rich, velvety texture. Made from sustainably sourced cocoa beans.', 67, 0, '2026-06-27 08:16:37'),
(7, 2, 'Prawn Crackers', 3.80, 'Products/Mckl Prawn Crackers.webp', 'Local Fav', '60g', '290 kcal', 'Traditional Malaysian-style prawn crackers with an authentic savoury flavour. Light, airy, and addictive.', 106, 0, '2026-06-27 08:16:37'),
(8, 2, 'Honey Nut Trail Mix', 6.50, 'Products/Mckl Honey Nut.webp', 'Healthy', '100g', '460 kcal', 'A premium blend of almonds, cashews, walnuts, raisins, and honey-roasted peanuts.', 42, 0, '2026-06-27 08:16:37'),
(9, 3, 'Tuna Melt Sandwich', 8.90, 'Products/mckl Tuna Melt sandwhich.webp', 'Hot Pick', '200g', '340 kcal', 'Freshly made tuna sandwich with melted cheddar cheese, crisp lettuce, and creamy mayo on soft wholemeal bread.', 29, 1, '2026-06-27 08:16:37'),
(10, 3, 'Chicken Rice Bowl', 12.50, 'Products/Mckl Chicken Rice.webp', NULL, '350g', '520 kcal', 'Juicy roasted chicken over fragrant steamed rice with cucumber slices, chilli sauce, and dark soy drizzle.', 24, 0, '2026-06-27 08:16:37'),
(11, 3, 'Spicy Instant Noodles', 2.50, 'Products/MCkl Noodles.webp', 'Value', '85g', '380 kcal', 'Korean-inspired instant noodles with an intensely spicy broth. Ready in just 3 minutes.', 139, 1, '2026-06-27 08:16:37'),
(12, 4, 'Hand Sanitiser 60ml', 4.50, 'Products/Mckl Hand Sanitizer.webp', NULL, '60ml', 'N/A', '75% alcohol hand sanitiser with aloe vera gel. Kills 99.9% of germs instantly without water.', 89, 0, '2026-06-27 08:16:37'),
(13, 1, 'Sparkling Water 330ml', 2.80, 'Products/Mckl Sparkling Water.webp', NULL, '330ml', '0 kcal', 'Lightly carbonated sparkling mineral water with a crisp, clean finish. A fizzy zero-sugar refresher.', 73, 0, '2026-06-27 08:16:37'),
(14, 2, 'Butter Cookies', 5.20, 'Products/Mckl Butter Cookies.webp', NULL, '120g', '510 kcal', 'Classic Danish-style butter cookies, crisp and buttery. Great with coffee or as an afternoon treat.', 63, 0, '2026-06-27 08:16:37'),
(15, 4, 'Facial Tissue Pack', 3.20, 'Products/Mckl Facial Tissue.webp', NULL, '100 sheets', 'N/A', 'Soft 2-ply facial tissues in a travel-friendly pack. Gentle on skin, strong when wet.', 98, 0, '2026-06-27 08:16:37'),
(16, 3, 'Egg Mayo Onigiri', 4.80, 'Products/Mckl Onigiri.webp', 'New', '120g', '210 kcal', 'Japanese rice ball wrapped in crisp seaweed with a creamy egg mayo filling. A light, satisfying snack or quick meal.', 53, 1, '2026-06-27 08:16:37'),
(17, 2, 'Chocolate Chip Muffin', 4.30, 'Products/Mckl Muffin.webp', NULL, '90g', '370 kcal', 'Soft, freshly baked muffin loaded with melty chocolate chips. The perfect sweet treat with your morning coffee.', 58, 0, '2026-06-27 08:16:37');
