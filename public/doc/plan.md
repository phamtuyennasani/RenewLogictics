Plan Nâng Cấp
Bây giờ chúng ta sẽ thiết kế 2 menu là: sender và receiver
2 Menu này sẽ được lưu tại table member. Check Xem đã có Model Member chưa, chưa có thì tạo, có rồi thì edit lại cho phù hợp
Gồm các thông tin:
Sender gồm có: id,company_name,fullname,email,phone,id_sale,id_ctv(tùy chọn),id_province,id_ward,type(sender)
    + Các trường bắt buộc company_name,fullname,phone,id_sale.
    + Mỗi Sender sẽ thuộc 1 sale
    + Mỗi sender có thể chọn CTV phụ trách hoặc ko cần.

Receiver gồm có: id,company_name,fullname,email,phone,id_sale,id_ctv(tùy chọn),id_sender(tùy chọn),country_id,state,cities,postcode
    + Các trường bắt buộc company_name,fullname,phone,id_sale.
    + Mỗi Receiver có thể chọn CVT phụ trách hoặc không
    + Mỗi Receiver có thể chọn Sender phụ trách hoặc không
    + country_id Cho chọn select Quốc gia.
    + state là cho nhập nhưng có datalist các State theo country_id để chọn rồi người dùng có thể edit nếu muốn
    + cities là cho nhập nhưng có datalist các cities theo state để chọn rồi người dùng có thể edit nếu muốn
    + postcode cho nhập

