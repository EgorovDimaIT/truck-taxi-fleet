SELECT w.uuid AS waypoint_uuid, w.`order` AS ord, pl.street1, pl.city, pl.uuid AS place_uuid
FROM waypoints w
JOIN places pl ON pl.uuid = w.place_uuid
WHERE w.payload_uuid = 'e0e5f0a3-d32a-4e68-bcab-c7ec1c961318'
ORDER BY ord;
