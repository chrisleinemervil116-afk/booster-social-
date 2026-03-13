-- Structure pour le site du Revendeur

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_api_key` varchar(255) DEFAULT NULL, -- TA Clé API (BlessPanel)
  `site_name` varchar(100) DEFAULT 'Mon SMM Panel',
  PRIMARY KEY (`id`)
);

INSERT INTO `settings` (`id`, `provider_api_key`) VALUES (1, '');

CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_service_id` int(11) NOT NULL, -- ID original sur BlessPanel
  `name` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `rate` decimal(10,4) NOT NULL, -- Leur prix de vente
  `min` int(11) NOT NULL,
  `max` int(11) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
);

CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `link` text NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,4) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `provider_order_id` int(11) DEFAULT NULL, -- ID de commande chez BlessPanel
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);